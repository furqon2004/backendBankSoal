<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Material;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class QuizService
{
    /**
     * Start a quiz attempt for a user.
     *
     * @throws Exception
     */
    public function start(User $user, int $materialId): array
    {
        $material = Material::active()
            ->withCount('questions')
            ->findOrFail($materialId);

        if ($material->questions_count === 0) {
            throw new Exception('This material has no questions yet.');
        }

        // Check if user already attempted this material
        $existingAttempt = QuizAttempt::where('user_id', $user->id)
            ->where('material_id', $materialId)
            ->first();

        if ($existingAttempt) {
            if ($existingAttempt->isCompleted()) {
                throw new Exception('You have already completed this quiz. Only one attempt is allowed.');
            }

            // Return existing in-progress attempt
            $existingAttempt->load(['material.questions' => function ($query) {
                $query->select('id', 'material_id', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d');
            }]);

            return [
                'attempt' => $existingAttempt,
                'questions' => $existingAttempt->material->questions,
                'resumed' => true,
            ];
        }

        // Create new attempt
        $attempt = QuizAttempt::create([
            'user_id' => $user->id,
            'material_id' => $materialId,
            'total_questions' => $material->questions_count,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Load questions without correct answers
        $questions = $material->questions()
            ->select('id', 'material_id', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d')
            ->inRandomOrder()
            ->get();

        return [
            'attempt' => $attempt,
            'questions' => $questions,
            'resumed' => false,
        ];
    }

    /**
     * Submit quiz answers and calculate score.
     *
     * @throws Exception
     */
    public function submit(User $user, int $attemptId, array $answers): QuizAttempt
    {
        return DB::transaction(function () use ($user, $attemptId, $answers) {
            $attempt = QuizAttempt::where('id', $attemptId)
                ->where('user_id', $user->id)
                ->with('material.questions')
                ->firstOrFail();

            if ($attempt->isCompleted()) {
                throw new Exception('This quiz has already been submitted.');
            }

            $questions = $attempt->material->questions->keyBy('id');
            $correctCount = 0;
            $answerRecords = [];

            foreach ($answers as $answer) {
                $questionId = $answer['question_id'];
                $userAnswer = strtolower($answer['answer']);

                $question = $questions->get($questionId);

                if (! $question) {
                    continue; // Skip invalid question IDs
                }

                $isCorrect = $question->correct_answer === $userAnswer;

                if ($isCorrect) {
                    $correctCount++;
                }

                $answerRecords[] = [
                    'attempt_id' => $attempt->id,
                    'question_id' => $questionId,
                    'user_answer' => $userAnswer,
                    'is_correct' => $isCorrect,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk insert answers
            foreach (array_chunk($answerRecords, 50) as $chunk) {
                Answer::insert($chunk);
            }

            // Calculate score
            $totalQuestions = $questions->count();
            $score = $totalQuestions > 0 ? ($correctCount / $totalQuestions) * 100 : 0;

            // Update attempt
            $attempt->update([
                'score' => round($score, 2),
                'correct_answers' => $correctCount,
                'total_questions' => $totalQuestions,
                'status' => 'completed',
                'submitted_at' => now(),
            ]);

            $attempt->load(['answers.question', 'material']);

            return $attempt;
        });
    }

    /**
     * Get quiz history for a user.
     */
    public function history(User $user)
    {
        return QuizAttempt::where('user_id', $user->id)
            ->with('material:id,title')
            ->latest('submitted_at')
            ->paginate(15);
    }

    /**
     * Get a specific quiz attempt with details.
     *
     * @throws Exception
     */
    public function find(User $user, int $attemptId): QuizAttempt
    {
        $attempt = QuizAttempt::where('id', $attemptId)
            ->where('user_id', $user->id)
            ->with(['material:id,title', 'answers.question'])
            ->firstOrFail();

        return $attempt;
    }
}
