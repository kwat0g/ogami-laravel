<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\Models\OvertimeRequest;
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

        /** @var array<string, int> Map work_date → overtime_minutes from attendance logs */
        $logOtByDate = [];

        foreach ($logs as $log) {
            if ($log->is_present) {
                $ctx->daysWorked++;
                $ctx->daysLateMinutes += $log->tardiness_minutes ?? 0;
                $ctx->undertimeMinutes += $log->undertime_minutes ?? 0;
                $ctx->overtimeRegularMinutes += $log->overtime_minutes ?? 0;
                $ctx->nightDiffMinutes += $log->night_diff_minutes ?? 0;

                $dateKey = $log->work_date instanceof \DateTimeInterface
                    ? $log->work_date->format('Y-m-d')
                    : (string) $log->work_date;
                $logOtByDate[$dateKey] = (int) ($log->overtime_minutes ?? 0);

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

        // ATT-OT-001: Overlay with approved OvertimeRequests so that employees
        // with a fully-approved OT request are guaranteed their OT pay even when
        // the biometric import did not capture overtime_minutes on the attendance log.
        $approvedOtRequests = OvertimeRequest::where('employee_id', $ctx->employee->id)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$ctx->run->cutoff_start, $ctx->run->cutoff_end])
            ->whereNotNull('approved_minutes')
            ->get();

        foreach ($approvedOtRequests as $otReq) {
            $dateKey = $otReq->work_date instanceof \DateTimeInterface
                ? $otReq->work_date->format('Y-m-d')
                : (string) $otReq->work_date;
            $logOt = $logOtByDate[$dateKey] ?? 0;
            $approvedOt = (int) $otReq->approved_minutes;

            if ($approvedOt > $logOt) {
                $ctx->overtimeRegularMinutes += ($approvedOt - $logOt);
                $logOtByDate[$dateKey] = $approvedOt; // prevent double-counting on subsequent requests for same date
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
