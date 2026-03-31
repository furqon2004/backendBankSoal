<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\MaterialController as AdminMaterialController;
use App\Http\Controllers\Api\Admin\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ── Public (Unauthenticated) ────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// ── Authenticated ───────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth & Profile
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/refresh-token', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'update']);

    // Materials (user-facing)
    Route::get('/materials', [MaterialController::class, 'index']);
    Route::get('/materials/{id}', [MaterialController::class, 'show']);
    Route::get('/materials/{id}/questions-by-section', [MaterialController::class, 'questionsBySection']);

    // Quiz
    Route::post('/quiz/start', [QuizController::class, 'start']);
    Route::post('/quiz/submit', [QuizController::class, 'submit']);
    Route::get('/quiz/history', [QuizController::class, 'history']);
    Route::get('/quiz/{id}', [QuizController::class, 'show'])->name('quiz.show');

    // ── Admin ───────────────────────────────────────────────────────────
    Route::middleware('admin')->prefix('admin')->group(function () {

        // User Management
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);

        // Material Management
        Route::post('/materials', [AdminMaterialController::class, 'store'])->middleware('throttle:ai.generate');
        Route::post('/materials/{id}/generate-questions', [AdminMaterialController::class, 'generateQuestions'])->middleware('throttle:ai.generate');
        Route::get('/materials/{id}/media', [AdminMaterialController::class, 'media']);
        Route::get('/materials/{id}/questions-by-section', [AdminMaterialController::class, 'questionsBySection']);

        // Analytics & Reports
        Route::get('/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/reports/materials', [ReportController::class, 'materials']);
        Route::get('/reports/users', [ReportController::class, 'users']);
        Route::get('/reports/errors', [ReportController::class, 'errors']);
        Route::get('/reports/export', [ReportController::class, 'export']);
    });
});
