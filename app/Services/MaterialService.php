<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Question;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class MaterialService
{
    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * List active materials with question counts (paginated).
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Material::active()
            ->withCount('questions')
            ->with('creator:id,name')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find a material by ID with its questions.
     */
    public function find(int $id): Material
    {
        return Material::with(['questions', 'creator:id,name'])
            ->withCount('questions')
            ->findOrFail($id);
    }

    /**
     * Create a new material and auto-generate questions via AI.
     */
    public function create(array $data, User $admin): array
    {
        return DB::transaction(function () use ($data, $admin) {
            $material = Material::create([
                'title' => $data['title'],
                'content' => $data['content'],
                'created_by' => $admin->id,
                'attempt_limit' => $data['attempt_limit'] ?? 1,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $questionsGenerated = 0;
            $aiError = null;

            try {
                $count = $data['question_count'] ?? null;
                $questionsData = $this->aiService->generateQuestions(
                    $material->content,
                    $material->title,
                    $count
                );

                $this->saveQuestions($material, $questionsData);
                $questionsGenerated = count($questionsData);

            } catch (Exception $e) {
                Log::error('AI question generation failed for material #' . $material->id . ': ' . $e->getMessage());
                $aiError = $e->getMessage();
            }

            $material->load('questions');
            $material->loadCount('questions');

            return [
                'material' => $material,
                'questions_generated' => $questionsGenerated,
                'ai_error' => $aiError,
            ];
        });
    }

    /**
     * Regenerate questions for an existing material using AI.
     */
    public function regenerateQuestions(int $materialId, ?int $count = null): array
    {
        $material = Material::findOrFail($materialId);

        // Delete existing questions
        $material->questions()->delete();

        try {
            $questionsData = $this->aiService->generateQuestions(
                $material->content,
                $material->title,
                $count
            );

            $this->saveQuestions($material, $questionsData);

            $material->load('questions');
            $material->loadCount('questions');

            return [
                'material' => $material,
                'questions_generated' => count($questionsData),
                'ai_error' => null,
            ];

        } catch (Exception $e) {
            Log::error('AI question regeneration failed for material #' . $materialId . ': ' . $e->getMessage());

            return [
                'material' => $material,
                'questions_generated' => 0,
                'ai_error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Manually add questions to a material (bulk insert).
     */
    public function addQuestions(int $materialId, array $questions): Material
    {
        $material = Material::findOrFail($materialId);
        $this->saveQuestions($material, $questions);

        $material->load('questions');
        $material->loadCount('questions');

        return $material;
    }

    /**
     * Save questions to a material.
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

        // Bulk insert in chunks of 50
        foreach (array_chunk($records, 50) as $chunk) {
            Question::insert($chunk);
        }
    }
}
