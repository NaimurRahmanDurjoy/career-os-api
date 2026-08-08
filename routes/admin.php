<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;

Route::post('/login', [AdminAuthController::class, 'login']);

Route::middleware('auth:admin')->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
