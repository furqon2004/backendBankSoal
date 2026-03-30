<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Material;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminService
{
    /**
     * Get dashboard statistics.
     */
    public function dashboardStats(): array
    {
        return [
            'total_users' => User::where('role', 'user')->count(),
            'total_admins' => User::where('role', 'admin')->count(),
            'total_materials' => Material::count(),
            'active_materials' => Material::active()->count(),
            'total_questions' => DB::table('questions')->count(),
            'total_attempts' => QuizAttempt::where('status', 'completed')->count(),
            'average_score' => round(QuizAttempt::where('status', 'completed')->avg('score') ?? 0, 2),
            'in_progress_attempts' => QuizAttempt::where('status', 'in_progress')->count(),
        ];
    }

    /**
     * Get per-material statistics.
     */
    public function materialReports(): array
    {
        $materials = Material::withCount(['questions', 'quizAttempts as total_attempts' => function ($query) {
                $query->where('status', 'completed');
            }])
            ->withAvg(['quizAttempts as average_score' => function ($query) {
                $query->where('status', 'completed');
            }], 'score')
            ->with('creator:id,name')
            ->get();

        return $materials->map(function ($material) {
            return [
                'id' => $material->id,
                'title' => $material->title,
                'created_by' => $material->creator->name ?? 'Unknown',
                'questions_count' => $material->questions_count,
                'total_attempts' => $material->total_attempts,
                'average_score' => round($material->average_score ?? 0, 2),
                'is_active' => $material->is_active,
                'created_at' => $material->created_at->toDateTimeString(),
            ];
        })->toArray();
    }

    /**
     * Get per-user performance statistics.
     */
    public function userReports(): array
    {
        $users = User::where('role', 'user')
            ->withCount(['quizAttempts as total_attempts' => function ($query) {
                $query->where('status', 'completed');
            }])
            ->withAvg(['quizAttempts as average_score' => function ($query) {
                $query->where('status', 'completed');
            }], 'score')
            ->get();

        return $users->map(function ($user) {
            $highestScore = QuizAttempt::where('user_id', $user->id)
                ->where('status', 'completed')
                ->max('score');

            $lowestScore = QuizAttempt::where('user_id', $user->id)
                ->where('status', 'completed')
                ->min('score');

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'total_attempts' => $user->total_attempts,
                'average_score' => round($user->average_score ?? 0, 2),
                'highest_score' => round($highestScore ?? 0, 2),
                'lowest_score' => round($lowestScore ?? 0, 2),
            ];
        })->toArray();
    }

    /**
     * Get most incorrectly answered questions (error analysis).
     */
    public function errorReports(): array
    {
        $questions = DB::table('answers')
            ->join('questions', 'answers.question_id', '=', 'questions.id')
            ->join('materials', 'questions.material_id', '=', 'materials.id')
            ->select(
                'questions.id',
                'questions.question_text',
                'questions.correct_answer',
                'questions.explanation',
                'materials.title as material_title',
                DB::raw('COUNT(*) as total_answered'),
                DB::raw('SUM(CASE WHEN answers.is_correct = 0 THEN 1 ELSE 0 END) as incorrect_count'),
                DB::raw('ROUND(SUM(CASE WHEN answers.is_correct = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as error_rate')
            )
            ->groupBy('questions.id', 'questions.question_text', 'questions.correct_answer', 'questions.explanation', 'materials.title')
            ->orderByDesc('error_rate')
            ->limit(50)
            ->get();

        return $questions->toArray();
    }
}
