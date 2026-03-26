<?php

declare(strict_types=1);

namespace App\Listeners\Attendance;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Events\Leave\LeaveRequestDecided;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Creates or updates AttendanceLog records for each day covered by an approved
 * leave request, so the payroll pipeline does not count those days as absences.
 *
 * LVE-ATT-001: Approved leave days must appear in the attendance ledger with
 *              480 worked minutes so payroll treats them as paid leave days.
 *
 * Idempotent: Uses updateOrCreate keyed on (employee_id, work_date) to avoid
 * overwriting biometric records that already exist.
 */
final class RecordLeaveAttendanceCorrection implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(LeaveRequestDecided $event): void
    {
        // Only process approved decisions
        if ($event->decision !== 'approved') {
            return;
        }

        $leaveRequest = $event->request;

        if ($leaveRequest->date_from === null || $leaveRequest->date_to === null) {
            return;
        }

        $leaveTypeName = $leaveRequest->leaveType?->name ?? 'Approved Leave';
        $employeeId = $leaveRequest->employee_id;

        $period = CarbonPeriod::create(
            $leaveRequest->date_from,
            $leaveRequest->date_to
        );

        foreach ($period as $date) {
            // Skip weekends — if a company doesn't observe leave on weekends,
            // those days don't need attendance correction.
            if ($date->isWeekend()) {
                continue;
            }

            AttendanceLog::updateOrCreate(
                ['employee_id' => $employeeId, 'work_date' => $date->toDateString()],
                [
                    'source' => 'leave_correction',
                    'is_present' => true,
                    'is_absent' => false,
                    'worked_minutes' => 480,
                    'late_minutes' => 0,
                    'undertime_minutes' => 0,
                    'remarks' => "On approved leave: {$leaveTypeName}",
                ],
            );
        }
    }
}
