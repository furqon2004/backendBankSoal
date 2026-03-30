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

        return $this->success([
            'user' => new UserResource($user),
            'statistics' => $stats,
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
