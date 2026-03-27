<?php

declare(strict_types=1);

use App\Http\Controllers\Leave\LeaveRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

Route::middleware(['auth:sanctum', 'module_access:leaves'])->group(function () {
    // Leave request CRUD + workflow actions
    Route::get('requests', [LeaveRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/team', [LeaveRequestController::class, 'team'])
        ->middleware('permission:leaves.view_team')
        ->name('requests.team');
    Route::post('requests', [LeaveRequestController::class, 'store'])->name('requests.store');
    Route::get('requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('requests.show');

    // Step 2 — Department Head approves (SoD: approver cannot be submitter)
    Route::patch('requests/{leaveRequest}/head-approve', [LeaveRequestController::class, 'headApprove'])
        ->middleware(['permission:leaves.head_approve', 'sod:leave_requests,head_approve', 'throttle:api-action'])
        ->name('requests.head_approve');

    // Step 3 — Plant Manager checks (SoD: checker cannot be submitter)
    Route::patch('requests/{leaveRequest}/manager-check', [LeaveRequestController::class, 'managerCheck'])
        ->middleware(['permission:leaves.manager_check', 'sod:leave_requests,manager_check', 'throttle:api-action'])
        ->name('requests.manager_check');

    // Step 4 — GA Officer processes (SoD: processor cannot be submitter)
    Route::patch('requests/{leaveRequest}/ga-process', [LeaveRequestController::class, 'gaProcess'])
        ->middleware(['permission:leaves.ga_process', 'sod:leave_requests,ga_process', 'throttle:api-action'])
        ->name('requests.ga_process');

    // Step 5 — VP notes (SoD: VP cannot be submitter)
    Route::patch('requests/{leaveRequest}/vp-note', [LeaveRequestController::class, 'vpNote'])
        ->middleware(['permission:leaves.vp_note', 'sod:leave_requests,vp_note', 'throttle:api-action'])
        ->name('requests.vp_note');

    // Reject (any current-step approver)
    Route::patch('requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])
        ->middleware('throttle:api-action')
        ->name('requests.reject');

    // Batch operations (must be above parameterised routes)
    Route::patch('requests/batch-head-approve', [LeaveRequestController::class, 'batchHeadApprove'])
        ->middleware(['permission:leaves.head_approve', 'throttle:api-action'])
        ->name('requests.batch_head_approve');
    Route::patch('requests/batch-reject', [LeaveRequestController::class, 'batchReject'])
        ->middleware('throttle:api-action')
        ->name('requests.batch_reject');

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

    // ── Leave Export (CSV) ───────────────────────────────────────────────────
    Route::get('export', function (Request $request): StreamedResponse {
        $query = DB::table('leave_requests')
            ->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
            ->select(
                'employees.employee_code',
                DB::raw("concat(employees.first_name, ' ', employees.last_name) as full_name"),
                'leave_types.name as leave_type',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.total_days',
                'leave_requests.status',
                'leave_requests.created_at',
            );

        if ($request->filled('date_from')) {
            $query->where('leave_requests.start_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('leave_requests.end_date', '<=', $request->input('date_to'));
        }
        if ($request->filled('department_id')) {
            $query->where('employees.department_id', $request->input('department_id'));
        }

        $rows = $query->orderBy('leave_requests.start_date', 'desc')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Employee Code', 'Name', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Status', 'Filed On']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->employee_code, $r->full_name, $r->leave_type, $r->start_date, $r->end_date, $r->total_days, $r->status, $r->created_at]);
            }
            fclose($out);
        }, 'leave_report_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    })->middleware('permission:hr.full_access')->name('export');
});
