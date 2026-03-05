<?php

declare(strict_types=1);

use App\Http\Controllers\Leave\LeaveRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Leave Domain Routes  (prefix: v1/leave, name: v1.leave.)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Leave request CRUD + workflow actions
    Route::get('requests', [LeaveRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/team', [LeaveRequestController::class, 'team'])
        ->middleware('permission:leaves.view_team')
        ->name('requests.team');
    Route::get('requests/pending-executive', [LeaveRequestController::class, 'pendingExecutive'])
        ->middleware('permission:leaves.executive_approve')
        ->name('requests.pending_executive');
    Route::post('requests', [LeaveRequestController::class, 'store'])->name('requests.store');
    Route::get('requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('requests.show');
    Route::patch('requests/{leaveRequest}/supervisor-approve', [LeaveRequestController::class, 'supervisorApprove'])
        ->middleware(['permission:leaves.supervise', 'throttle:api-action'])
        ->name('requests.supervisor_approve');
    Route::patch('requests/{leaveRequest}/head-approve', [LeaveRequestController::class, 'supervisorApprove'])
        ->middleware(['permission:leaves.supervise', 'throttle:api-action'])
        ->name('requests.head_approve');
    Route::patch('requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])
        ->middleware(['sod:leaves,approve', 'throttle:api-action'])  // checks leaves.file_own (SOD-002)
        ->name('requests.approve');
    Route::patch('requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])
        ->middleware('throttle:api-action')
        ->name('requests.reject');
    Route::patch('requests/{leaveRequest}/executive-approve', [LeaveRequestController::class, 'executiveApprove'])
        ->middleware(['permission:leaves.executive_approve', 'throttle:api-action'])
        ->name('requests.executive_approve');
    Route::patch('requests/{leaveRequest}/executive-reject', [LeaveRequestController::class, 'executiveReject'])
        ->middleware(['permission:leaves.executive_approve', 'throttle:api-action'])
        ->name('requests.executive_reject');
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
