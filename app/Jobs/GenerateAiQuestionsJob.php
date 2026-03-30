<?php

namespace App\Jobs;

use App\Models\Material;
use App\Models\Question;
use App\Services\AiService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAiQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $materialId,
        public ?int $questionCount = null
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(AiService $aiService): void
    {
        $material = Material::find($this->materialId);

        if (! $material) {
            return;
        }

        try {
            $questionsData = $aiService->generateQuestions(
                $material->content,
                $material->title,
                $this->questionCount
            );

            $this->saveQuestions($material, $questionsData);
            
            Log::info("Successfully generated " . count($questionsData) . " AI questions for material #{$material->id}");

        } catch (Exception $e) {
            Log::error('AI question generation failed for material #' . $material->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Save questions to database
     */
    protected function saveQuestions(Material $material, array $questionsData): void
    {
        $records = [];

        foreach ($questionsData as $q) {
            $records[] = [
                'material_id' => $material->id,
                'question_text' => $q['question_text'],
                'option_a' => $q['option_a'],
                'option_b' => $q['option_b'],
                'option_c' => $q['option_c'],
                'option_d' => $q['option_d'],
                'correct_answer' => strtolower($q['correct_answer']),
                'explanation' => $q['explanation'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert
        foreach (array_chunk($records, 50) as $chunk) {
            Question::insert($chunk);
        }
    }
}
