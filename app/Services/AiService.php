<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialMedia;
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

    /** Section definitions (label in Indonesian + Japanese) */
    public const SECTIONS = [
        1 => [
            'label'       => '絵を見て、正しいものを選びましょう。 Lihatlah ilustrasi, kemudian pilihlah kata yang benar.',
            'description' => 'Soal bergambar: siswa melihat gambar dan memilih kata/ungkapan Jepang yang paling tepat menggambarkan gambar tersebut.',
            'required'    => true,
        ],
        2 => [
            'label'       => 'Bacalah kalimatnya, kemudian pilihlah kata yang paling sesuai dengan bagian yang bergaris bawah.',
            'description' => 'Fill-in-the-blank kosakata: kalimat bahasa Jepang dengan satu bagian kosong, siswa memilih kata yang paling sesuai.',
            'required'    => true,
        ],
        3 => [
            'label'       => 'Pilihlah cara baca yang tepat dari Kanji yang digarisbawahi.',
            'description' => 'Cara baca kanji: kalimat dengan kanji digarisbawahi, siswa memilih furigana (cara baca hiragana) yang benar.',
            'required'    => true,
        ],
        4 => [
            'label'       => 'Bacalah kalimatnya, kemudian pilihlah Kanji yang paling sesuai dengan bagian yang bergaris bawah.',
            'description' => 'Penulisan kanji: kalimat dengan kata hiragana yang digarisbawahi, siswa memilih kanji yang tepat.',
            'required'    => true,
        ],
        5 => [
            'label'       => 'Bacalah percakapan berikut, kemudian pilihlah ungkapan yang paling sesuai dengan bagian yang bergaris bawah.',
            'description' => 'Percakapan: dialog pendek dengan satu bagian kosong, siswa memilih ungkapan/respons yang paling tepat.',
            'required'    => true,
        ],
        6 => [
            'label'       => 'Simaklah audionya, kemudian jawab pertanyaannya.',
            'description' => 'Pemahaman mendengarkan: berdasarkan transkrip percakapan audio dalam materi, siswa menjawab pertanyaan.',
            'required'    => false, // Only if PDF has audio
        ],
        7 => [
            'label'       => 'Bacalah wacana berikut, kemudian jawab pertanyaannya.',
            'description' => 'Pemahaman bacaan: teks/wacana panjang dalam bahasa Jepang, siswa menjawab pertanyaan pemahaman.',
            'required'    => true,
        ],
    ];

    public function __construct()
    {
        $this->apiKey      = config('ai.gemini.api_key');
        $this->model       = config('ai.gemini.model');
        $this->apiUrl      = config('ai.gemini.api_url');
        $this->temperature = config('ai.gemini.temperature');
        $this->maxTokens   = config('ai.gemini.max_tokens');
    }

    /**
     * Generate structured sectioned quiz questions with image/audio awareness.
     *
     * @param Material $material         The material model (with media loaded)
     * @param array    $extractedImages  MaterialMedia image records (ordered)
     * @param bool     $hasAudio         Whether the material has audio
     * @param int|null $totalCount       Total number of questions (23-32)
     * @return array   Flat array of question records ready for DB insertion
     * @throws Exception
     */
    public function generateSectionedQuestions(
        Material $material,
        array $extractedImages = [],
        bool $hasAudio = false,
        ?int $totalCount = null
    ): array {
        if (empty($this->apiKey)) {
            throw new Exception('Gemini API key is not configured. Set GEMINI_API_KEY in .env');
        }

        if ($totalCount === null) {
            $totalCount = rand(
                config('ai.questions.min_count', 23),
                config('ai.questions.max_count', 32)
            );
        }

        // Distribute questions across sections
        $distribution = $this->distributeQuestions($totalCount, $hasAudio);

        $prompt = $this->buildSectionedPrompt(
            $material->content,
            $material->title,
            $extractedImages,
            $hasAudio,
            $distribution
        );

        $response = $this->callGeminiApi($prompt);
        $parsed   = $this->parseSectionedResponse($response);

        return $this->flattenSections($parsed, $material->id, $extractedImages);
    }

    /**
     * Distribute total question count across available sections.
     *
     * @return array<int, int> section_number => question_count
     */
    protected function distributeQuestions(int $total, bool $hasAudio): array
    {
        // Active sections
        $active = array_keys(array_filter(self::SECTIONS, fn($s) => $s['required'] || ($s['required'] === false && $hasAudio)));
        if (! $hasAudio) {
            $active = array_filter($active, fn($k) => $k !== 6);
        }
        $active = array_values($active);

        $sectionCount = count($active);
        $base         = (int) floor($total / $sectionCount);
        $remainder    = $total % $sectionCount;

        $distribution = [];
        foreach ($active as $i => $sectionNum) {
            $distribution[$sectionNum] = $base + ($i < $remainder ? 1 : 0);
            // Clamp between 3..5
            $distribution[$sectionNum] = max(3, min(5, $distribution[$sectionNum]));
        }

        return $distribution;
    }

    /**
     * Build the sectioned question generation prompt.
     */
    protected function buildSectionedPrompt(
        string $content,
        string $title,
        array  $extractedImages,
        bool   $hasAudio,
        array  $distribution
    ): string {
        $imageCount   = count($extractedImages);
        $imageContext = $imageCount > 0
            ? "Materi ini memiliki {$imageCount} gambar yang telah diekstrak. Untuk Section 1, gunakan gambar-gambar tersebut (referensikan dengan image_index 0, 1, 2, dst)."
            : "Materi ini TIDAK memiliki gambar yang berhasil diekstrak. Untuk Section 1, buat soal bergambar konseptual berdasarkan kosakata benda dari materi (image_index tetap 0, 1, 2, dst — gambar akan diasosiasikan nanti).";

        $audioContext = $hasAudio
            ? "Materi ini MEMILIKI audio embedded. Buat Section 6 berdasarkan transkrip percakapan yang ada dalam konten materi."
            : "Materi ini TIDAK memiliki audio. Jangan buat Section 6.";

        $sectionsSpec = '';
        foreach ($distribution as $sectionNum => $count) {
            $sec = self::SECTIONS[$sectionNum];
            $sectionsSpec .= "\n- Section {$sectionNum} ({$count} soal): {$sec['description']}";
        }

        $jsonExample = $this->buildJsonExample($distribution, $hasAudio);

        return <<<PROMPT
Kamu adalah pembuat soal ujian Bahasa Jepang (JLPT-style) yang sangat ahli. Buat soal berdasarkan materi berikut, dibagi dalam beberapa section terstruktur.

Judul Materi: {$title}

Isi Materi:
{$content}

---

KONTEKS MEDIA:
{$imageContext}
{$audioContext}

---

SECTION YANG HARUS DIBUAT:
{$sectionsSpec}

---

ATURAN PENTING:
1. Setiap soal WAJIB memiliki tepat 4 pilihan jawaban (option_a, option_b, option_c, option_d)
2. correct_answer harus huruf kecil: "a", "b", "c", atau "d"
3. Soal harus relevan dengan konten materi yang diberikan
4. Section 3 (cara baca kanji): question_text berisi kalimat dengan kanji, pilihan jawaban adalah furigana/hiragana
5. Section 4 (kanji): question_text berisi kalimat dengan kata hiragana, pilihan jawaban adalah kanji
6. Section 1 (gambar): sertakan field image_index (integer, mulai dari 0) yang mengacu ke gambar ke-berapa
7. Section 6 (audio): hanya buat jika ada audio. question_text mengacu pada percakapan audio
8. Distribusikan tingkat kesulitan: mudah, sedang, dan sulit
9. Explanation harus membantu siswa memahami jawaban

FORMAT RESPONSE (JSON murni, tanpa markdown code block):
{$jsonExample}
PROMPT;
    }

    /**
     * Build an example JSON structure for the prompt.
     */
    protected function buildJsonExample(array $distribution, bool $hasAudio): string
    {
        $sections = [];

        foreach ($distribution as $sectionNum => $count) {
            $sec         = self::SECTIONS[$sectionNum];
            $exampleQ    = $this->buildExampleQuestion($sectionNum);
            $questionsArr = '[' . $exampleQ . ', ...]';

            $sections[] = <<<JSON
    {
      "section": {$sectionNum},
      "label": "{$sec['label']}",
      "questions": {$questionsArr}
    }
JSON;
        }

        $sectionsJson = implode(",\n", $sections);

        return <<<JSON
{
  "sections": [
{$sectionsJson}
  ]
}
JSON;
    }

    /**
     * Build an example question JSON string for a given section.
     */
    protected function buildExampleQuestion(int $section): string
    {
        return match ($section) {
            1 => '{"question_text": "Benda apa yang ada di gambar ini?", "image_index": 0, "option_a": "飾り", "option_b": "お守り", "option_c": "調味料", "option_d": "人形", "correct_answer": "b", "explanation": "Gambar menunjukkan お守り (jimat pelindung)"}',
            2 => '{"question_text": "毎朝、コーヒーを___飲みます。", "option_a": "ゆっくり", "option_b": "はやく", "option_c": "たくさん", "option_d": "すこし", "correct_answer": "a", "explanation": "ゆっくり berarti pelan-pelan/perlahan, cocok untuk menggambarkan cara minum kopi"}',
            3 => '{"question_text": "彼女は毎日学校へ行きます。（学校）", "option_a": "がっこう", "option_b": "がくこう", "option_c": "かっこう", "option_d": "かくこう", "correct_answer": "a", "explanation": "学校 dibaca がっこう (gakkou) artinya sekolah"}',
            4 => '{"question_text": "毎朝、はやおきします。（はやおき）", "option_a": "速起", "option_b": "早起", "option_c": "早起き", "option_d": "速起き", "correct_answer": "c", "explanation": "早起き (hayaoki) berarti bangun pagi, kanji 早 berarti awal/cepat"}',
            5 => '{"question_text": "A: すみません、駅はどこですか。\nB: ___", "option_a": "ええ、そうです", "option_b": "あそこですよ", "option_c": "いいえ、ちがいます", "option_d": "どういたしまして", "correct_answer": "b", "explanation": "あそこですよ (di sana) adalah respons yang tepat saat ditanya lokasi"}',
            6 => '{"question_text": "Dalam percakapan audio, apa yang dibicarakan oleh A dan B?", "option_a": "Rencana liburan", "option_b": "Jadwal pertemuan", "option_c": "Menu makanan", "option_d": "Cuaca hari ini", "correct_answer": "b", "explanation": "Percakapan membahas jadwal pertemuan besok"}',
            7 => '{"question_text": "Berdasarkan wacana, mengapa tokoh utama pergi ke Jepang?", "option_a": "Berlibur", "option_b": "Belajar bahasa", "option_c": "Bekerja", "option_d": "Mengikuti lomba", "correct_answer": "b", "explanation": "Disebutkan dalam teks bahwa ia pergi untuk belajar bahasa Jepang"}',
            default => '{"question_text": "...", "option_a": "...", "option_b": "...", "option_c": "...", "option_d": "...", "correct_answer": "a", "explanation": "..."}',
        };
    }

    /**
     * Call the Gemini API with retry logic.
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
                'temperature'      => $this->temperature,
                'maxOutputTokens'  => $this->maxTokens,
                'responseMimeType' => 'application/json',
            ],
        ];

        $maxRetries    = 3;
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

                $errorBody    = $response->json();
                $errorMessage = $errorBody['error']['message'] ?? 'Unknown API error';
                throw new Exception("Gemini API error (HTTP {$response->status()}): {$errorMessage}");

            } catch (Exception $e) {
                $lastException = $e;
                Log::warning("Gemini API attempt {$attempt}/{$maxRetries} failed: " . $e->getMessage());

                if ($attempt < $maxRetries) {
                    sleep(2 * $attempt);
                }
            }
        }

        throw new Exception('Failed after ' . $maxRetries . ' attempts: ' . $lastException->getMessage());
    }

    /**
     * Parse and validate the sectioned JSON response from Gemini.
     *
     * @throws Exception
     */
    protected function parseSectionedResponse(string $response): array
    {
        $cleaned = trim($response);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        $data = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse AI sectioned response', ['response' => substr($response, 0, 500)]);
            throw new Exception('Failed to parse AI response: ' . json_last_error_msg());
        }

        if (! isset($data['sections']) || ! is_array($data['sections']) || empty($data['sections'])) {
            throw new Exception('AI response missing "sections" array');
        }

        return $data['sections'];
    }

    /**
     * Flatten sectioned questions into a flat array for DB insertion,
     * resolving image_index references to actual MaterialMedia URLs.
     *
     * @param array           $sections        Parsed sections from AI
     * @param int             $materialId
     * @param MaterialMedia[] $extractedImages Ordered image media records
     * @return array Flat array of question records
     */
    protected function flattenSections(array $sections, int $materialId, array $extractedImages): array
    {
        $records    = [];
        $validAnswers = ['a', 'b', 'c', 'd'];

        foreach ($sections as $sec) {
            $sectionNum   = (int) ($sec['section'] ?? 0);
            $sectionLabel = $sec['label'] ?? (self::SECTIONS[$sectionNum]['label'] ?? '');
            $questions    = $sec['questions'] ?? [];

            if ($sectionNum < 1 || $sectionNum > 7 || empty($questions)) {
                continue;
            }

            foreach ($questions as $itemNum => $q) {
                // Validate required fields
                if (empty($q['question_text']) || empty($q['correct_answer'])) {
                    continue;
                }

                $correctAnswer = strtolower(trim($q['correct_answer']));
                if (! in_array($correctAnswer, $validAnswers)) {
                    continue;
                }

                // Resolve image URL for section 1
                $imageUrl = null;
                if ($sectionNum === 1 && isset($q['image_index'])) {
                    $idx      = (int) $q['image_index'];
                    $imageUrl = isset($extractedImages[$idx]) ? $extractedImages[$idx]->file_url : null;
                }

                // Resolve audio URL for section 6 (first audio if multiple)
                $audioUrl = null;
                if ($sectionNum === 6 && ! empty($extractedImages)) {
                    // Audio is passed separately; this is handled in the job
                }

                $records[] = [
                    'material_id'   => $materialId,
                    'section'       => $sectionNum,
                    'section_label' => $sectionLabel,
                    'question_text' => trim($q['question_text']),
                    'option_a'      => trim($q['option_a'] ?? ''),
                    'option_b'      => trim($q['option_b'] ?? ''),
                    'option_c'      => trim($q['option_c'] ?? ''),
                    'option_d'      => trim($q['option_d'] ?? ''),
                    'correct_answer' => $correctAnswer,
                    'explanation'   => trim($q['explanation'] ?? ''),
                    'image_url'     => $imageUrl,
                    'audio_url'     => null, // Set in job after media extraction
                    'item_number'   => $itemNum + 1,
                ];
            }
        }

        if (empty($records)) {
            throw new Exception('AI returned valid JSON but produced 0 usable questions');
        }

        return $records;
    }

    /**
     * Legacy method — kept for backward compatibility.
     *
     * @deprecated Use generateSectionedQuestions() instead.
     * @throws Exception
     */
    public function generateQuestions(string $materialContent, string $materialTitle, ?int $count = null): array
    {
        $material         = new Material();
        $material->content = $materialContent;
        $material->title   = $materialTitle;
        $material->id      = 0;

        return $this->generateSectionedQuestions($material, [], false, $count);
    }
}
