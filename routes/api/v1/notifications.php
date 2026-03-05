<?php

declare(strict_types=1);

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| In-App Notification Center — Route Group
|--------------------------------------------------------------------------
| Prefix  : /api/v1/notifications
| Name    : v1.notifications.
| Auth    : auth:sanctum (all routes)
|
| Each user can only see and manage their own notifications (scoped by
| the NotificationController to auth()->user()->notifications()).
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    Route::put('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::put('/{id}/read', [NotificationController::class, 'markRead'])->name('read');
    Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
});
