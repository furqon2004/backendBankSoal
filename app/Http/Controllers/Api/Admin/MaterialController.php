<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateQuestionsRequest;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Resources\MaterialDetailResource;
use App\Http\Resources\MaterialMediaResource;
use App\Services\MaterialService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class MaterialController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MaterialService $materialService
    ) {}

    /**
     * Create a new material (PDF or text).
     * AI auto-generates 23–32 structured sectioned questions in background.
     */
    public function store(StoreMaterialRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Pass the uploaded PDF file object (not just path)
        if ($request->hasFile('pdf')) {
            $data['pdf'] = $request->file('pdf');
        }

        $result = $this->materialService->create($data, $request->user());

        $hasPdf  = isset($data['pdf']);
        $message = $hasPdf
            ? "Material created. {$result['questions_generated']}. PDF media is being extracted in the background."
            : "Material created. {$result['questions_generated']}.";

        return $this->success(
            new MaterialDetailResource($result['material']),
            $message,
            201
        );
    }

    /**
     * Regenerate questions for an existing material using AI.
     */
    public function generateQuestions(GenerateQuestionsRequest $request, int $id): JsonResponse
    {
        $count  = $request->validated()['count'] ?? null;
        $result = $this->materialService->regenerateQuestions($id, $count);

        return $this->success(
            new MaterialDetailResource($result['material']),
            $result['questions_generated']
        );
    }

    /**
     * Get all extracted media (images & audio) for a material.
     */
    public function media(int $id): JsonResponse
    {
        $data = $this->materialService->getMedia($id);

        return $this->success([
            'material_id'     => $data['material_id'],
            'has_audio'       => $data['has_audio'],
            'media_extracted' => $data['media_extracted'],
            'images'          => MaterialMediaResource::collection($data['images']),
            'audio'           => MaterialMediaResource::collection($data['audio']),
            'images_count'    => $data['images']->count(),
            'audio_count'     => $data['audio']->count(),
        ], 'Material media retrieved successfully');
    }

    /**
     * Get questions grouped by section for a material.
     */
    public function questionsBySection(int $id): JsonResponse
    {
        $sections = $this->materialService->getQuestionsBySection($id);

        $totalQuestions = collect($sections)->sum('questions_count');

        return $this->success([
            'material_id'     => $id,
            'total_questions' => $totalQuestions,
            'sections_count'  => count($sections),
            'sections'        => $sections,
        ], 'Questions by section retrieved successfully');
    }
}
