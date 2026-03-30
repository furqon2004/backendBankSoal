<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_text' => $this->question_text,
            'option_a' => $this->option_a,
            'option_b' => $this->option_b,
            'option_c' => $this->option_c,
            'option_d' => $this->option_d,
            'correct_answer' => $this->when(
                $request->user()?->isAdmin() || $this->shouldShowAnswer($request),
                $this->correct_answer
            ),
            'explanation' => $this->when(
                $request->user()?->isAdmin() || $this->shouldShowAnswer($request),
                $this->explanation
            ),
        ];
    }

    /**
     * Determine if the answer should be shown (e.g., after quiz submission).
     */
    protected function shouldShowAnswer(Request $request): bool
    {
        // Show answer when loaded through a completed attempt's answers
        return $request->routeIs('quiz.show');
    }
}
