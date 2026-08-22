<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\OAuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ApplicationNoteController;
use App\Http\Controllers\Api\AiJobMatchController;
use App\Http\Controllers\Api\AiToolsController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\PreparationTrackerController;
use App\Http\Controllers\Api\AiMockTestController;
use App\Http\Controllers\Api\CoverLetterController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\WebhookController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('throttle:forgot_password')->group(function () {
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
});

Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback']);

Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureUserIsActive::class])->group(function () {
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
    Route::post('/jobs/{id}/cover-letter', [JobApplicationController::class, 'saveCoverLetter']);
    Route::delete('/jobs/{id}', [JobApplicationController::class, 'destroy']);
    
    // Application Notes
    Route::get('/jobs/{jobId}/notes', [ApplicationNoteController::class, 'index']);
    Route::post('/jobs/{jobId}/notes', [ApplicationNoteController::class, 'store']);
    Route::put('/notes/{id}', [ApplicationNoteController::class, 'update']);
    Route::delete('/notes/{id}', [ApplicationNoteController::class, 'destroy']);
    
    // Cover Letters
    Route::get('/jobs/{jobId}/cover-letters', [CoverLetterController::class, 'show']);
    Route::post('/jobs/{jobId}/cover-letters/generate', [CoverLetterController::class, 'generate'])->middleware('throttle:ai_tools');
    Route::put('/jobs/{jobId}/cover-letters', [CoverLetterController::class, 'update']);
    
    // AI Job Matching
    Route::post('/jobs/{id}/ai-match', [AiJobMatchController::class, 'analyze'])->middleware(\App\Http\Middleware\CheckSubscription::class.':job_match');
    Route::get('/jobs/{id}/ai-match', [AiJobMatchController::class, 'show'])->middleware(\App\Http\Middleware\CheckSubscription::class.':job_match');
    
    // Preparation Tracker
    Route::apiResource('preparation-trackers', PreparationTrackerController::class);

    // AI Mock Tests
    Route::middleware('throttle:mock_tests')->group(function () {
        Route::get('/mock-tests', [AiMockTestController::class, 'index']);
        Route::post('/mock-tests/generate', [AiMockTestController::class, 'generate']);
        Route::get('/mock-tests/{id}', [AiMockTestController::class, 'show']);
        Route::post('/mock-tests/{id}/submit', [AiMockTestController::class, 'submit']);
        Route::delete('/mock-tests/{id}', [AiMockTestController::class, 'destroy']);
    });

    // AI Tools (Cover Letter, Interview Prep, etc)
    Route::middleware('throttle:ai_tools')->group(function () {
        Route::post('/ai-tools/cover-letter', [AiToolsController::class, 'coverLetter']);
        Route::post('/ai-tools/stateless-cover-letter', [AiToolsController::class, 'statelessCoverLetter']);
        Route::post('/ai-tools/interview-questions', [AiToolsController::class, 'interviewQuestions']);
        Route::post('/ai-tools/evaluate-match', [AiToolsController::class, 'evaluateMatch'])->middleware(\App\Http\Middleware\CheckSubscription::class.':job_match');
        Route::post('/ai-tools/parse-jd', [AiToolsController::class, 'parseJd']);
        Route::post('/ai-tools/rejection-analysis', [AiToolsController::class, 'rejectionAnalysis']);
        Route::post('/ai-tools/salary-negotiation', [AiToolsController::class, 'salaryNegotiation']);
    });

    // Reminders
    Route::get('/reminders/upcoming', [ReminderController::class, 'upcoming']);
    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::post('/reminders', [ReminderController::class, 'store']);
    Route::put('/reminders/{id}', [ReminderController::class, 'update']);
    Route::delete('/reminders/{id}', [ReminderController::class, 'destroy']);
    
    // Billing
    Route::get('/billing/plans', [BillingController::class, 'getPlans']);
    Route::post('/billing/checkout', [BillingController::class, 'initiateCheckout']);
    Route::get('/billing/history', [BillingController::class, 'history']);
});

// Webhooks
Route::post('/webhooks/sslcommerz', [WebhookController::class, 'handleSslCommerzIPN']);
Route::post('/webhooks/sslcommerz/success', [WebhookController::class, 'sslCommerzSuccess']);
Route::post('/webhooks/sslcommerz/fail', [WebhookController::class, 'sslCommerzFail']);
Route::post('/webhooks/sslcommerz/cancel', [WebhookController::class, 'sslCommerzCancel']);
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripeWebhook']);
Route::get('/webhooks/stripe/success', [WebhookController::class, 'stripeSuccess']);
Route::get('/webhooks/stripe/cancel', [WebhookController::class, 'stripeCancel']);