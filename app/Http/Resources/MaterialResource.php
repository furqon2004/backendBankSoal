<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'has_audio'       => $this->has_audio,
            'media_extracted' => $this->media_extracted,
            'created_by'      => new UserResource($this->whenLoaded('creator')),
            'attempt_limit'   => $this->attempt_limit,
            'is_active'       => $this->is_active,
            'questions_count' => $this->whenCounted('questions'),
            'created_at'      => $this->created_at->toDateTimeString(),
            'updated_at'      => $this->updated_at->toDateTimeString(),
        ];
    }
}
