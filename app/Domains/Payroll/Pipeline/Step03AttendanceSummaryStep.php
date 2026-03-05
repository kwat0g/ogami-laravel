<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;

/**
 * Step 03 — Attendance Summary.
 *
 * Reads all AttendanceLog rows for the employee within the cutoff window and
 * aggregates: days worked/absent, tardiness/undertime minutes, overtime minutes
 * by type, night-differential minutes, and holiday day counts.
 *
 * Also loads approved paid/unpaid leave requests for the period.
 */
final class Step03AttendanceSummaryStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $logs = AttendanceLog::where('employee_id', $ctx->employee->id)
            ->whereBetween('work_date', [$ctx->run->cutoff_start, $ctx->run->cutoff_end])
            ->get();

        $ctx->attendanceLogs = $logs;

        foreach ($logs as $log) {
            if ($log->is_present) {
                $ctx->daysWorked++;
                $ctx->daysLateMinutes += $log->tardiness_minutes ?? 0;
                $ctx->undertimeMinutes += $log->undertime_minutes ?? 0;
                $ctx->overtimeRegularMinutes += $log->overtime_minutes ?? 0;
                $ctx->nightDiffMinutes += $log->night_diff_minutes ?? 0;

                $holidayType = $log->holiday_type ?? null;
                if ($holidayType === 'regular') {
                    $ctx->regularHolidayDays++;
                } elseif (in_array($holidayType, ['special', 'special_non_working', 'special_working'], true)) {
                    $ctx->specialHolidayDays++;
                }
            } else {
                $ctx->daysAbsent++;
            }
        }

        // Approved leave requests within the cutoff window
        $paidLeave = LeaveRequest::where('employee_id', $ctx->employee->id)
            ->where('status', 'approved')
            ->whereBetween('date_from', [$ctx->run->cutoff_start, $ctx->run->cutoff_end])
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', true))
            ->get();

        $unpaidLeave = LeaveRequest::where('employee_id', $ctx->employee->id)
            ->where('status', 'approved')
            ->whereBetween('date_from', [$ctx->run->cutoff_start, $ctx->run->cutoff_end])
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', false))
            ->get();

        $ctx->paidLeaveRequests = $paidLeave;
        $ctx->unpaidLeaveRequests = $unpaidLeave;
        $ctx->leaveDaysPaid = (int) $paidLeave->sum('total_days');
        $ctx->leaveDaysUnpaid = (int) $unpaidLeave->sum('total_days');

        return $next($ctx);
    }
}
