<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\JobApplicationController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/resumes', [ResumeController::class, 'index']);
    Route::post('/resumes/upload', [ResumeController::class, 'upload']);
    Route::get('/resumes/{id}', [ResumeController::class, 'show']);
    Route::put('/resumes/{id}', [ResumeController::class, 'update']);
    
    // Job Application Routes
    Route::get('/jobs', [JobApplicationController::class, 'index']);
    Route::post('/jobs', [JobApplicationController::class, 'store']);
    Route::patch('/jobs/{id}/status', [JobApplicationController::class, 'updateStatus']);
    Route::delete('/jobs/{id}', [JobApplicationController::class, 'destroy']);
});