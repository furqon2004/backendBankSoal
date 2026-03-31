<?php

namespace App\Services;

use App\Jobs\ExtractPdfMediaJob;
use App\Jobs\GenerateAiQuestionsJob;
use App\Models\Material;
use App\Models\MaterialMedia;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
     * List ALL materials (admin use, including inactive).
     */
    public function listAll(int $perPage = 15): LengthAwarePaginator
    {
        return Material::withCount('questions')
            ->with('creator:id,name')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find a material by ID with its questions.
     */
    public function find(int $id): Material
    {
        return Material::with(['questions' => fn ($q) => $q->orderBy('section')->orderBy('item_number'), 'creator:id,name', 'media'])
            ->withCount('questions')
            ->findOrFail($id);
    }

    /**
     * Create a new material.
     * If a PDF is provided, extract media then generate AI questions.
     * If only text content is provided, generate AI questions directly.
     */
    public function create(array $data, User $admin): array
    {
        return DB::transaction(function () use ($data, $admin) {
            $pdfPath = null;

            // Handle PDF upload
            if (isset($data['pdf']) && $data['pdf'] instanceof UploadedFile) {
                $pdfPath = $data['pdf']->store("materials/pdfs", 'local');
                Log::info("MaterialService: PDF uploaded to {$pdfPath}");
            }

            // Determine content
            $content = $data['content'] ?? '';

            $material = Material::create([
                'title'           => $data['title'],
                'content'         => $content,
                'created_by'      => $admin->id,
                'attempt_limit'   => $data['attempt_limit'] ?? 1,
                'is_active'       => $data['is_active'] ?? true,
                'pdf_path'        => $pdfPath,
                'has_audio'       => false,
                'media_extracted' => false,
            ]);

            $count = $data['question_count'] ?? null;

            if ($pdfPath) {
                // Has PDF → extract media first, then generate questions
                ExtractPdfMediaJob::dispatch($material->id, $count);
                $status = 'In progress (Extracting PDF media, then generating questions)';
            } else {
                // No PDF → generate questions directly from text
                GenerateAiQuestionsJob::dispatch($material->id, $count);
                $status = 'In progress (Background AI generation started)';
            }

            $material->load('questions');
            $material->loadCount('questions');

            return [
                'material'            => $material,
                'questions_generated' => $status,
                'ai_error'            => null,
            ];
        });
    }

    /**
     * Regenerate questions for an existing material using AI in background.
     * Deletes existing questions first.
     */
    public function regenerateQuestions(int $materialId, ?int $count = null): array
    {
        $material = Material::findOrFail($materialId);

        // Delete existing questions
        $material->questions()->delete();

        if ($material->pdf_path && ! $material->media_extracted) {
            // PDF not yet processed → extract first
            ExtractPdfMediaJob::dispatch($material->id, $count);
            $status = 'In progress (Re-extracting PDF media, then re-generating questions)';
        } else {
            // Already have media (or no PDF) → go straight to AI
            GenerateAiQuestionsJob::dispatch($material->id, $count);
            $status = 'In progress (Background AI re-generation started)';
        }

        $material->load('questions');
        $material->loadCount('questions');

        return [
            'material'            => $material,
            'questions_generated' => $status,
            'ai_error'            => null,
        ];
    }

    /**
     * Get all media for a material grouped by type.
     */
    public function getMedia(int $materialId): array
    {
        $material = Material::findOrFail($materialId);

        return [
            'material_id'     => $material->id,
            'has_audio'       => $material->has_audio,
            'media_extracted' => $material->media_extracted,
            'images'          => MaterialMedia::where('material_id', $materialId)
                ->where('type', 'image')
                ->orderBy('order')
                ->get(),
            'audio'           => MaterialMedia::where('material_id', $materialId)
                ->where('type', 'audio')
                ->orderBy('order')
                ->get(),
        ];
    }

    /**
     * Get questions grouped by section for a material.
     */
    public function getQuestionsBySection(int $materialId): array
    {
        $material  = Material::findOrFail($materialId);
        $questions = $material->questions()
            ->orderBy('section')
            ->orderBy('item_number')
            ->get();

        $grouped = $questions->groupBy('section');
        $result  = [];

        foreach ($grouped as $sectionNum => $sectionQuestions) {
            $label = $sectionQuestions->first()->section_label
                ?? (AiService::SECTIONS[$sectionNum]['label'] ?? "Section {$sectionNum}");

            $result[] = [
                'section'        => $sectionNum,
                'label'          => $label,
                'questions_count' => $sectionQuestions->count(),
                'questions'      => $sectionQuestions->values(),
            ];
        }

        return $result;
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
     * Save questions to a material (manual insertions).
     */
    protected function saveQuestions(Material $material, array $questionsData): void
    {
        $records = [];
        $now     = now();

        foreach ($questionsData as $itemNum => $q) {
            $records[] = [
                'material_id'   => $material->id,
                'section'       => $q['section']        ?? null,
                'section_label' => $q['section_label'] ?? null,
                'question_text' => $q['question_text'],
                'option_a'      => $q['option_a']      ?? '',
                'option_b'      => $q['option_b']      ?? '',
                'option_c'      => $q['option_c']      ?? '',
                'option_d'      => $q['option_d']      ?? '',
                'correct_answer' => strtolower($q['correct_answer']),
                'explanation'   => $q['explanation']  ?? null,
                'image_url'     => $q['image_url']    ?? null,
                'audio_url'     => $q['audio_url']    ?? null,
                'item_number'   => $q['item_number']  ?? ($itemNum + 1),
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        foreach (array_chunk($records, 50) as $chunk) {
            Question::insert($chunk);
        }
    }
}
