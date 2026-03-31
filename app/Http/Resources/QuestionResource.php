<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin      = $request->user()?->isAdmin();
        $showAnswer   = $isAdmin || $this->shouldShowAnswer($request);

        return [
            'id'           => $this->id,
            'section'      => $this->section,
            'section_label' => $this->section_label,
            'item_number'  => $this->item_number,
            'question_text' => $this->question_text,
            'image_url'    => $this->image_url,
            'audio_url'    => $this->audio_url,
            'option_a'     => $this->option_a,
            'option_b'     => $this->option_b,
            'option_c'     => $this->option_c,
            'option_d'     => $this->option_d,
            'correct_answer' => $this->when($showAnswer, $this->correct_answer),
            'explanation'   => $this->when($showAnswer, $this->explanation),
        ];
    }

    /**
     * Determine if the answer should be shown (e.g., after quiz submission).
     */
    protected function shouldShowAnswer(Request $request): bool
    {
        return $request->routeIs('quiz.show');
    }
}
