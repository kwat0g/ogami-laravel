<?php

declare(strict_types=1);

use App\Http\Controllers\Leave\LeaveRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Leave Domain Routes  (prefix: v1/leave, name: v1.leave.)
|
| 4-step approval chain (form AD-084-00):
|   POST   requests                 Employee submits
|   PATCH  requests/{id}/head-approve     Step 2 — Dept Head
|   PATCH  requests/{id}/manager-check   Step 3 — Plant Manager
|   PATCH  requests/{id}/ga-process      Step 4 — GA Officer
|   PATCH  requests/{id}/vp-note         Step 5 — VP
|   PATCH  requests/{id}/reject          Any current approver can reject
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Leave request CRUD + workflow actions
    Route::get('requests', [LeaveRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/team', [LeaveRequestController::class, 'team'])
        ->middleware('permission:leaves.view_team')
        ->name('requests.team');
    Route::post('requests', [LeaveRequestController::class, 'store'])->name('requests.store');
    Route::get('requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('requests.show');

    // Step 2 — Department Head approves
    Route::patch('requests/{leaveRequest}/head-approve', [LeaveRequestController::class, 'headApprove'])
        ->middleware(['permission:leaves.head_approve', 'throttle:api-action'])
        ->name('requests.head_approve');

    // Step 3 — Plant Manager checks
    Route::patch('requests/{leaveRequest}/manager-check', [LeaveRequestController::class, 'managerCheck'])
        ->middleware(['permission:leaves.manager_check', 'throttle:api-action'])
        ->name('requests.manager_check');

    // Step 4 — GA Officer processes (sets action_taken + balance snapshot)
    Route::patch('requests/{leaveRequest}/ga-process', [LeaveRequestController::class, 'gaProcess'])
        ->middleware(['permission:leaves.ga_process', 'throttle:api-action'])
        ->name('requests.ga_process');

    // Step 5 — VP notes (deducts balance for approved_with_pay)
    Route::patch('requests/{leaveRequest}/vp-note', [LeaveRequestController::class, 'vpNote'])
        ->middleware(['permission:leaves.vp_note', 'throttle:api-action'])
        ->name('requests.vp_note');

    // Reject (any current-step approver)
    Route::patch('requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])
        ->middleware('throttle:api-action')
        ->name('requests.reject');

    Route::delete('requests/{leaveRequest}', [LeaveRequestController::class, 'cancel'])->name('requests.cancel');

    // Leave balances
    Route::get('balances', [LeaveRequestController::class, 'balances'])->name('balances.index');
    Route::post('balances', [LeaveRequestController::class, 'storeBalance'])
        ->middleware('permission:leave_balances.manage')
        ->name('balances.store');
    Route::patch('balances/{leaveBalance}', [LeaveRequestController::class, 'updateBalance'])
        ->middleware('permission:leave_balances.manage')
        ->name('balances.update');

    // Monthly calendar view — approved requests + public holidays by department
    Route::get('calendar', [LeaveRequestController::class, 'calendar'])->name('calendar');
});
