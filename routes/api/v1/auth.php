<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ChangePasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes — /api/v1/auth/*
|--------------------------------------------------------------------------
*/

// Public (unauthenticated)
// throttle:login = 10 attempts/min per IP (defence-in-depth; AuthService handles 5-attempt lockout)
Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

// Fully authenticated endpoints
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('me', [AuthController::class, 'me'])->name('auth.me');
    Route::get('me/permissions', [AuthController::class, 'permissions'])->name('auth.me.permissions');
    Route::post('change-password', ChangePasswordController::class)->name('auth.change-password');
});
