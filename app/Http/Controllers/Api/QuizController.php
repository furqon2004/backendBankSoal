<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartQuizRequest;
use App\Http\Requests\SubmitQuizRequest;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\QuizAttemptResource;
use App\Services\QuizService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected QuizService $quizService
    ) {}

    /**
     * Start a quiz attempt.
     */
    public function start(StartQuizRequest $request): JsonResponse
    {
        try {
            $result = $this->quizService->start(
                $request->user(),
                $request->validated()['material_id']
            );

            return $this->success([
                'attempt' => new QuizAttemptResource($result['attempt']),
                'questions' => QuestionResource::collection($result['questions']),
                'resumed' => $result['resumed'],
            ], $result['resumed'] ? 'Quiz resumed' : 'Quiz started');

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Submit quiz answers.
     */
    public function submit(SubmitQuizRequest $request): JsonResponse
    {
        try {
            $attempt = $this->quizService->submit(
                $request->user(),
                $request->validated()['attempt_id'],
                $request->validated()['answers']
            );

            return $this->success(
                new QuizAttemptResource($attempt),
                'Quiz submitted successfully. Score: ' . $attempt->score . '%'
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Get quiz attempt history for the authenticated user.
     */
    public function history(Request $request): JsonResponse
    {
        $attempts = $this->quizService->history($request->user());

        return $this->success(
            QuizAttemptResource::collection($attempts)->response()->getData(true),
            'Quiz history retrieved successfully'
        );
    }

    /**
     * Get details of a specific quiz attempt.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $attempt = $this->quizService->find($request->user(), $id);

            return $this->success(
                new QuizAttemptResource($attempt),
                'Quiz attempt retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->error('Quiz attempt not found', 404);
        }
    }
}
