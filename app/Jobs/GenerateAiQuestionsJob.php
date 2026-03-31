<?php

namespace App\Jobs;

use App\Models\Material;
use App\Models\MaterialMedia;
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

    /** @var int Retry up to 3 times */
    public int $tries = 3;

    /** @var int Timeout in seconds — AI can take a while */
    public int $timeout = 180;

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
            Log::warning("GenerateAiQuestionsJob: material #{$this->materialId} not found.");
            return;
        }

        try {
            // Load extracted images & audio from DB
            $imageMedia = MaterialMedia::where('material_id', $this->materialId)
                ->where('type', 'image')
                ->orderBy('order')
                ->get()
                ->all();

            $audioMedia = MaterialMedia::where('material_id', $this->materialId)
                ->where('type', 'audio')
                ->orderBy('order')
                ->first();

            $hasAudio = $material->has_audio && $audioMedia !== null;

            Log::info("GenerateAiQuestionsJob: generating sectioned questions for material #{$this->materialId}. Images: " . count($imageMedia) . ", HasAudio: " . ($hasAudio ? 'yes' : 'no'));

            // Generate sectioned questions via AI
            $questionsData = $aiService->generateSectionedQuestions(
                $material,
                $imageMedia,
                $hasAudio,
                $this->questionCount
            );

            // Inject audio URL into section 6 questions
            if ($hasAudio && $audioMedia) {
                foreach ($questionsData as &$q) {
                    if (($q['section'] ?? 0) === 6) {
                        $q['audio_url'] = $audioMedia->file_url;
                    }
                }
                unset($q);
            }

            $this->saveQuestions($questionsData);

            Log::info("GenerateAiQuestionsJob: saved " . count($questionsData) . " sectioned questions for material #{$this->materialId}");

        } catch (Exception $e) {
            Log::error("GenerateAiQuestionsJob: failed for material #{$this->materialId}: " . $e->getMessage());
            throw $e; // Re-throw so the queue can retry
        }
    }

    /**
     * Save questions to the database in bulk.
     */
    protected function saveQuestions(array $questionsData): void
    {
        $records = [];
        $now     = now();

        foreach ($questionsData as $q) {
            $records[] = [
                'material_id'   => $q['material_id']   ?? $this->materialId,
                'section'       => $q['section']        ?? null,
                'section_label' => $q['section_label'] ?? null,
                'question_text' => $q['question_text'],
                'option_a'      => $q['option_a']      ?? '',
                'option_b'      => $q['option_b']      ?? '',
                'option_c'      => $q['option_c']      ?? '',
                'option_d'      => $q['option_d']      ?? '',
                'correct_answer' => strtolower($q['correct_answer']),
                'explanation'   => $q['explanation']   ?? null,
                'image_url'     => $q['image_url']     ?? null,
                'audio_url'     => $q['audio_url']     ?? null,
                'item_number'   => $q['item_number']   ?? null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        foreach (array_chunk($records, 50) as $chunk) {
            Question::insert($chunk);
        }
    }
}
