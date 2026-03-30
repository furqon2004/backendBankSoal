<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\QuizAttempt;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponse;

    /**
     * Get user profile with statistics.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // Calculate statistics for the user
        $completedAttempts = QuizAttempt::where('user_id', $user->id)
            ->where('status', 'completed');

        $stats = [
            'total_attempts' => $completedAttempts->count(),
            'average_score' => round($completedAttempts->avg('score') ?? 0, 2),
            'highest_score' => round($completedAttempts->max('score') ?? 0, 2),
            'lowest_score' => round($completedAttempts->min('score') ?? 0, 2),
        ];

        // Ambil 5 riwayat pengerjaan terakhir untuk ditampilkan di dashboard
        $recentHistory = QuizAttempt::where('user_id', $user->id)
            ->with('material:id,title')
            ->latest('submitted_at')
            ->take(5)
            ->get()
            ->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'material_title' => $attempt->material->title ?? 'Unknown',
                    'score' => $attempt->score,
                    'total_questions' => $attempt->total_questions,
                    'correct_answers' => $attempt->correct_answers,
                    'status' => $attempt->status,
                    'submitted_at' => $attempt->submitted_at?->toDateTimeString(),
                ];
            });

        return $this->success([
            'user' => new UserResource($user),
            'statistics' => $stats,
            'recent_history' => $recentHistory, // List riwayat pengerjaan untuk dashboard
        ], 'Profile retrieved successfully');
    }

    /**
     * Update user profile information.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        if (isset($validated['password']) && !empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return $this->success(
            new UserResource($user),
            'Profile updated successfully'
        );
    }
}
