<?php

namespace App\Http\Resources;

use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Build questions_by_section from loaded questions
        $questionsBySection = $this->buildSectionedQuestions($request);

        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'content'          => $this->content,
            'pdf_path'         => $this->pdf_path,
            'has_audio'        => $this->has_audio,
            'media_extracted'  => $this->media_extracted,
            'created_by'       => new UserResource($this->whenLoaded('creator')),
            'attempt_limit'    => $this->attempt_limit,
            'is_active'        => $this->is_active,
            'questions_count'  => $this->whenCounted('questions'),
            'questions_by_section' => $questionsBySection,
            'questions'        => QuestionResource::collection($this->whenLoaded('questions')),
            'media'            => MaterialMediaResource::collection($this->whenLoaded('media')),
            'created_at'       => $this->created_at->toDateTimeString(),
            'updated_at'       => $this->updated_at->toDateTimeString(),
        ];
    }

    /**
     * Group loaded questions by section for structured response.
     */
    protected function buildSectionedQuestions(Request $request): array
    {
        if (! $this->relationLoaded('questions') || $this->questions->isEmpty()) {
            return [];
        }

        $grouped = $this->questions->groupBy('section');
        $result  = [];

        foreach ($grouped->sortKeys() as $sectionNum => $sectionQuestions) {
            $label = $sectionQuestions->first()->section_label
                ?? (AiService::SECTIONS[$sectionNum]['label'] ?? "Section {$sectionNum}");

            $result[] = [
                'section'         => (int) $sectionNum,
                'label'           => $label,
                'questions_count' => $sectionQuestions->count(),
                'has_audio'       => $sectionNum == 6,
                'has_images'      => $sectionNum == 1,
                'questions'       => QuestionResource::collection($sectionQuestions->values())->resolve($request),
            ];
        }

        return $result;
    }
}
