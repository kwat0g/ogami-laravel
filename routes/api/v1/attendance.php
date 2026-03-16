<?php

declare(strict_types=1);

use App\Domains\Attendance\Models\EmployeeShiftAssignment;
use App\Domains\Attendance\Models\ShiftSchedule;
use App\Domains\HR\Models\Employee;
use App\Http\Controllers\Attendance\AttendanceImportController;
use App\Http\Controllers\Attendance\AttendanceLogController;
use App\Http\Controllers\Attendance\OvertimeRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Attendance Domain Routes  (prefix: v1/attendance, name: v1.attendance.)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Dashboard: anomaly feed + OT approval queue (department-scoped)
    Route::get('dashboard', [AttendanceLogController::class, 'dashboard'])->name('dashboard');

    // Attendance logs (view)
    Route::get('logs', [AttendanceLogController::class, 'index'])->name('logs.index');
    Route::get('logs/team', [AttendanceLogController::class, 'team'])
        ->middleware('permission:attendance.view_team')
        ->name('logs.team');
    Route::get('logs/{attendanceLog}', [AttendanceLogController::class, 'show'])->name('logs.show');

    // Manual attendance entry (create/update)
    Route::post('logs', [AttendanceLogController::class, 'store'])
        ->middleware('permission:attendance.create')
        ->name('logs.store');
    Route::patch('logs/{attendanceLog}', [AttendanceLogController::class, 'update'])
        ->middleware('permission:attendance.update')
        ->name('logs.update');

    // Attendance CSV import
    Route::post('import', [AttendanceImportController::class, 'store'])->name('import');

    // Overtime requests CRUD + workflow actions
    Route::get('overtime-requests', [OvertimeRequestController::class, 'index'])->name('overtime.index');
    Route::get('overtime-requests/team', [OvertimeRequestController::class, 'team'])
        ->middleware('permission:overtime.view')
        ->name('overtime.team');
    // NOTE: static-segment routes must come before {overtimeRequest} to avoid conflation
    Route::get('overtime-requests/pending-executive', [OvertimeRequestController::class, 'pendingExecutive'])
        ->middleware('permission:overtime.executive_approve')
        ->name('overtime.pending-executive');
    Route::post('overtime-requests', [OvertimeRequestController::class, 'store'])->name('overtime.store');
    Route::get('overtime-requests/{overtimeRequest}', [OvertimeRequestController::class, 'show'])->name('overtime.show');
    // Supervisor/Head first-level endorsement (staff OT requests only) with SoD
    Route::patch('overtime-requests/{overtimeRequest}/supervisor-endorse', [OvertimeRequestController::class, 'supervisorEndorse'])
        ->middleware(['permission:overtime.supervise', 'sod:overtime_requests,supervisor_endorse', 'throttle:api-action'])
        ->name('overtime.supervisor-endorse');
    Route::patch('overtime-requests/{overtimeRequest}/head-endorse', [OvertimeRequestController::class, 'supervisorEndorse'])
        ->middleware(['permission:overtime.supervise', 'sod:overtime_requests,head_endorse', 'throttle:api-action'])
        ->name('overtime.head-endorse');
    // Manager final approval (staff after supervisor-endorsement, or supervisor directly)
    Route::patch('overtime-requests/{overtimeRequest}/approve', [OvertimeRequestController::class, 'approve'])
        ->middleware(['sod:overtime,approve', 'throttle:api-action'])  // checks overtime.submit (SOD-003)
        ->name('overtime.approve');
    Route::patch('overtime-requests/{overtimeRequest}/reject', [OvertimeRequestController::class, 'reject'])
        ->middleware('throttle:api-action')
        ->name('overtime.reject');
    Route::delete('overtime-requests/{overtimeRequest}', [OvertimeRequestController::class, 'cancel'])->name('overtime.cancel');
    // Executive approval for manager-filed OT requests with SoD
    Route::patch('overtime-requests/{overtimeRequest}/executive-approve', [OvertimeRequestController::class, 'executiveApprove'])
        ->middleware(['permission:overtime.executive_approve', 'sod:overtime_requests,executive_approve', 'throttle:api-action'])
        ->name('overtime.executive-approve');
    Route::patch('overtime-requests/{overtimeRequest}/executive-reject', [OvertimeRequestController::class, 'executiveReject'])
        ->middleware(['permission:overtime.executive_approve', 'throttle:api-action'])
        ->name('overtime.executive-reject');
    // Step 4: HR Officer review with SoD
    Route::patch('overtime-requests/{overtimeRequest}/officer-review', [OvertimeRequestController::class, 'officerReview'])
        ->middleware(['permission:overtime.supervise', 'sod:overtime_requests,officer_review', 'throttle:api-action'])
        ->name('overtime.officer-review');
    // Step 5: VP final approval with SoD
    Route::patch('overtime-requests/{overtimeRequest}/vp-approve', [OvertimeRequestController::class, 'vpApprove'])
        ->middleware(['permission:overtime.executive_approve', 'sod:overtime_requests,vp_approve', 'throttle:api-action'])
        ->name('overtime.vp-approve');

    // Shift schedules CRUD
    Route::get('shifts', function (Request $request) {
        abort_unless($request->user()->can('attendance.manage_shifts'), 403);

        return ShiftSchedule::orderBy('name')
            ->when($request->boolean('active_only', false), fn ($q) => $q->where('is_active', true))
            ->paginate((int) $request->query('per_page', '50'));
    })->name('shifts.index');

    Route::post('shifts', function (Request $request) {
        abort_unless($request->user()->can('attendance.manage_shifts'), 403);
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_minutes' => 'required|integer|min:0|max:120',
            'work_days' => 'required|string',
            'is_flexible' => 'boolean',
            'grace_period_minutes' => 'required|integer|min:0|max:60',
            'is_active' => 'boolean',
        ]);

        return response()->json(ShiftSchedule::create($validated), 201);
    })->name('shifts.store');

    Route::patch('shifts/{shift}', function (Request $request, ShiftSchedule $shift) {
        abort_unless($request->user()->can('attendance.manage_shifts'), 403);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'break_minutes' => 'sometimes|integer|min:0|max:120',
            'work_days' => 'sometimes|string',
            'is_flexible' => 'boolean',
            'grace_period_minutes' => 'sometimes|integer|min:0|max:60',
            'is_active' => 'boolean',
        ]);
        $shift->update($validated);

        return response()->json($shift->fresh());
    })->name('shifts.update');

    Route::delete('shifts/{shift}', function (Request $request, ShiftSchedule $shift) {
        abort_unless($request->user()->can('attendance.manage_shifts'), 403);
        $shift->delete();

        return response()->noContent();
    })->name('shifts.destroy');

    // Employee shift assignments
    Route::get('employees/{employee}/shift-assignments', function (Request $request, Employee $employee) {
        abort_unless($request->user()->can('attendance.manage_shifts'), 403);

        return $employee->shiftAssignments()->with('shiftSchedule')->orderByDesc('effective_from')->get();
    })->name('shift-assignments.index');

    Route::post('employees/{employee}/shift-assignments', function (Request $request, Employee $employee) {
        abort_unless($request->user()->can('attendance.manage_shifts'), 403);
        $validated = $request->validate([
            'shift_schedule_id' => 'required|integer|exists:shift_schedules,id',
            'effective_from' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);
        $assignment = $employee->shiftAssignments()->create([
            ...$validated,
            'assigned_by' => $request->user()->id,
        ]);

        return response()->json($assignment->load('shiftSchedule'), 201);
    })->name('shift-assignments.store');

    Route::delete('shift-assignments/{assignment}', function (Request $request, EmployeeShiftAssignment $assignment) {
        abort_unless($request->user()->can('attendance.manage_shifts'), 403);
        $assignment->delete();

        return response()->noContent();
    })->name('shift-assignments.destroy');

    // ── Attendance Summary Report ────────────────────────────────────────────
    Route::get('summary', function (Request $request): \Illuminate\Http\JsonResponse {
        abort_unless($request->user()->can('attendance.view_team') || $request->user()->can('hr.full_access'), 403);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->toDateString());

        $query = \App\Domains\Attendance\Models\AttendanceLog::query()
            ->whereBetween('work_date', [$from, $to])
            ->with('employee:id,first_name,last_name,department_id,employee_code');

        if ($request->filled('department_id')) {
            $deptId = (int) $request->input('department_id');
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        }

        $rows = $query->get()
            ->groupBy('employee_id')
            ->map(function ($logs, $employeeId) {
                $emp = $logs->first()->employee;
                return [
                    'employee_id'       => $employeeId,
                    'employee_code'     => $emp->employee_code ?? '',
                    'employee_name'     => trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '')),
                    'days_present'      => $logs->where('is_present', true)->count(),
                    'days_absent'       => $logs->where('is_absent', true)->count(),
                    'days_rest'         => $logs->where('is_rest_day', true)->count(),
                    'days_holiday'      => $logs->where('is_holiday', true)->count(),
                    'total_worked_min'  => $logs->sum('worked_minutes'),
                    'total_late_min'    => $logs->sum('late_minutes'),
                    'total_ut_min'      => $logs->sum('undertime_minutes'),
                    'total_ot_min'      => $logs->sum('overtime_minutes'),
                    'total_nd_min'      => $logs->sum('night_diff_minutes'),
                ];
            })
            ->values();

        return response()->json(['data' => $rows, 'from' => $from, 'to' => $to]);
    })->name('summary');

    // ── DTR Export (CSV download) ─────────────────────────────────────────────
    Route::get('dtr-export', function (Request $request): \Symfony\Component\HttpFoundation\StreamedResponse {
        abort_unless($request->user()->can('attendance.view_team') || $request->user()->can('hr.full_access'), 403);

        $employeeId = $request->integer('employee_id');
        abort_if($employeeId === 0, 422, 'employee_id is required.');

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->endOfMonth()->toDateString());

        $employee = Employee::findOrFail($employeeId);
        $logs = \App\Domains\Attendance\Models\AttendanceLog::where('employee_id', $employeeId)
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->get();

        $filename = "DTR_{$employee->employee_code}_{$from}_to_{$to}.csv";

        return response()->streamDownload(function () use ($logs, $employee) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Daily Time Record — ' . trim($employee->first_name . ' ' . $employee->last_name) . ' (' . $employee->employee_code . ')']);
            fputcsv($out, []);
            fputcsv($out, ['Date', 'Time In', 'Time Out', 'Worked (hrs)', 'Late (min)', 'Undertime (min)', 'OT (min)', 'Night Diff (min)', 'Present', 'Absent', 'Rest Day', 'Holiday', 'Remarks']);

            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->work_date->toDateString(),
                    $log->time_in ?? '',
                    $log->time_out ?? '',
                    round($log->worked_minutes / 60, 2),
                    $log->late_minutes,
                    $log->undertime_minutes,
                    $log->overtime_minutes,
                    $log->night_diff_minutes,
                    $log->is_present ? 'Yes' : 'No',
                    $log->is_absent ? 'Yes' : 'No',
                    $log->is_rest_day ? 'Yes' : 'No',
                    $log->is_holiday ? ($log->holiday_type ?? 'Yes') : 'No',
                    $log->remarks ?? '',
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    })->name('dtr-export');
});
