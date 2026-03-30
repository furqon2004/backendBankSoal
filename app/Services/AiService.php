<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AiService
{
    protected string $apiKey;
    protected string $model;
    protected string $apiUrl;
    protected float $temperature;
    protected int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('ai.gemini.api_key');
        $this->model = config('ai.gemini.model');
        $this->apiUrl = config('ai.gemini.api_url');
        $this->temperature = config('ai.gemini.temperature');
        $this->maxTokens = config('ai.gemini.max_tokens');
    }

    /**
     * Generate quiz questions from material content using Gemini AI.
     *
     * @param string $materialContent The learning material text
     * @param string $materialTitle The title of the material
     * @param int|null $count Number of questions to generate (23-32)
     * @return array Array of question data ready for DB insertion
     * @throws Exception
     */
    public function generateQuestions(string $materialContent, string $materialTitle, ?int $count = null): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('Gemini API key is not configured. Set GEMINI_API_KEY in .env');
        }

        // Random count between 23-32 if not specified
        if ($count === null) {
            $count = rand(
                config('ai.questions.min_count', 23),
                config('ai.questions.max_count', 32)
            );
        }

        $prompt = $this->buildPrompt($materialContent, $materialTitle, $count);

        $response = $this->callGeminiApi($prompt);

        $questions = $this->parseResponse($response);

        // Validate we got the expected structure
        $this->validateQuestions($questions);

        return $questions;
    }

    /**
     * Build the prompt for question generation.
     */
    protected function buildPrompt(string $content, string $title, int $count): string
    {
        return <<<PROMPT
Kamu adalah seorang pembuat soal ujian yang ahli dan berpengalaman. Berdasarkan materi pembelajaran berikut, buatlah tepat {$count} soal pilihan ganda.

Judul Materi: {$title}

Isi Materi:
{$content}

Untuk setiap soal, berikan dalam format JSON:
- "question_text": Pertanyaan yang jelas dan spesifik
- "option_a": Pilihan jawaban A
- "option_b": Pilihan jawaban B
- "option_c": Pilihan jawaban C
- "option_d": Pilihan jawaban D
- "correct_answer": Jawaban yang benar (hanya huruf kecil: "a", "b", "c", atau "d")
- "explanation": Penjelasan singkat mengapa jawaban tersebut benar

Aturan penting:
1. Soal harus mencakup berbagai aspek dari materi
2. Variasikan tingkat kesulitan (mudah, sedang, sulit)
3. Uji pemahaman konsep, bukan hanya hafalan
4. Pastikan setiap pilihan jawaban masuk akal (tidak ada jawaban yang jelas salah)
5. Penjelasan harus membantu siswa memahami konsep

Kembalikan HANYA dalam format JSON array, tanpa teks tambahan apapun. Contoh format:
[
  {
    "question_text": "Pertanyaan di sini?",
    "option_a": "Pilihan A",
    "option_b": "Pilihan B",
    "option_c": "Pilihan C",
    "option_d": "Pilihan D",
    "correct_answer": "a",
    "explanation": "Penjelasan di sini"
  }
]
PROMPT;
    }

    /**
     * Call the Gemini API.
     *
     * @throws Exception
     */
    protected function callGeminiApi(string $prompt): string
    {
        $url = $this->apiUrl . $this->model . ':generateContent?key=' . $this->apiKey;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $this->temperature,
                'maxOutputTokens' => $this->maxTokens,
                'responseMimeType' => 'application/json',
            ],
        ];

        $maxRetries = 3;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(120)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url, $payload);

                if ($response->successful()) {
                    $body = $response->json();

                    if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
                        return $body['candidates'][0]['content']['parts'][0]['text'];
                    }

                    throw new Exception('Unexpected Gemini API response structure');
                }

                $errorBody = $response->json();
                $errorMessage = $errorBody['error']['message'] ?? 'Unknown API error';
                throw new Exception("Gemini API error (HTTP {$response->status()}): {$errorMessage}");

            } catch (Exception $e) {
                $lastException = $e;
                Log::warning("Gemini API attempt {$attempt}/{$maxRetries} failed: " . $e->getMessage());

                if ($attempt < $maxRetries) {
                    sleep(2 * $attempt); // Exponential backoff
                }
            }
        }

        throw new Exception('Failed to generate questions after ' . $maxRetries . ' attempts: ' . $lastException->getMessage());
    }

    /**
     * Parse the AI response into structured question data.
     *
     * @throws Exception
     */
    protected function parseResponse(string $response): array
    {
        // Clean the response — remove markdown code fences if present
        $cleaned = trim($response);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        $questions = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse AI response as JSON', ['response' => $response]);
            throw new Exception('Failed to parse AI-generated questions: ' . json_last_error_msg());
        }

        if (! is_array($questions) || empty($questions)) {
            throw new Exception('AI returned empty or invalid question data');
        }

        return $questions;
    }

    /**
     * Validate the structure of generated questions.
     *
     * @throws Exception
     */
    protected function validateQuestions(array $questions): void
    {
        $requiredFields = ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'];
        $validAnswers = ['a', 'b', 'c', 'd'];

        foreach ($questions as $index => $question) {
            foreach ($requiredFields as $field) {
                if (! isset($question[$field]) || empty(trim((string) $question[$field]))) {
                    throw new Exception("Question #{$index}: missing required field '{$field}'");
                }
            }

            if (! in_array(strtolower($question['correct_answer']), $validAnswers)) {
                throw new Exception("Question #{$index}: invalid correct_answer '{$question['correct_answer']}'. Must be a, b, c, or d.");
            }
        }
    }
}
