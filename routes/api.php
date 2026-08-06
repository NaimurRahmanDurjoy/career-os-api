<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\OAuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ApplicationNoteController;
use App\Http\Controllers\Api\AiJobMatchController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'profile']);
    Route::put('/user', [AuthController::class, 'update']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Resumes
    Route::get('/resumes', [ResumeController::class, 'index']);
    Route::post('/resumes', [ResumeController::class, 'upload']);
    Route::get('/resumes/{id}', [ResumeController::class, 'show']);
    Route::put('/resumes/{id}', [ResumeController::class, 'update']);
    Route::delete('/resumes/{id}', [ResumeController::class, 'destroy']);
    Route::patch('/resumes/{id}/primary', [ResumeController::class, 'setPrimary']);
    
    // Job Application Routes
    Route::get('/jobs', [JobApplicationController::class, 'index']);
    Route::post('/jobs', [JobApplicationController::class, 'store']);
    Route::patch('/jobs/{id}/status', [JobApplicationController::class, 'updateStatus']);
    Route::delete('/jobs/{id}', [JobApplicationController::class, 'destroy']);
    
    // Application Notes
    Route::get('/jobs/{jobId}/notes', [ApplicationNoteController::class, 'index']);
    Route::post('/jobs/{jobId}/notes', [ApplicationNoteController::class, 'store']);
    Route::put('/notes/{id}', [ApplicationNoteController::class, 'update']);
    Route::delete('/notes/{id}', [ApplicationNoteController::class, 'destroy']);
    
    // AI Job Matching
    Route::post('/jobs/{id}/ai-match', [AiJobMatchController::class, 'analyze']);
    Route::get('/jobs/{id}/ai-match', [AiJobMatchController::class, 'show']);
});