<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Enums\AttendanceStatus;
use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\Models\NightShiftConfig;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;

/**
 * Computes all derived time fields when an employee times out.
 *
 * Produces: worked_minutes, late_minutes, undertime_minutes,
 * overtime_minutes, night_diff_minutes, boolean flags, and
 * the richer attendance_status enum value.
 */
final class TimeComputationService implements ServiceContract
{
    /**
     * Compute all derived time fields for an attendance log.
     *
     * @return array{
     *   worked_minutes: int,
     *   late_minutes: int,
     *   undertime_minutes: int,
     *   overtime_minutes: int,
     *   night_diff_minutes: int,
     *   is_present: bool,
     *   is_absent: bool,
     *   attendance_status: string,
     * }
     */
    public function compute(AttendanceLog $log): array
    {
        $timeIn = Carbon::parse($log->time_in);
        $timeOut = Carbon::parse($log->time_out);

        // Handle overnight: if time_out is before time_in, it crossed midnight
        if ($timeOut->lt($timeIn)) {
            $timeOut->addDay();
        }

        $totalMinutes = (int) $timeIn->diffInMinutes($timeOut);

        // Resolve shift from employee's active assignment
        $shift = null;
        if ($log->employee_id) {
            $workDateStr = $log->work_date instanceof \DateTimeInterface
                ? $log->work_date->format('Y-m-d')
                : (string) $log->work_date;

            $shift = $log->employee?->shiftAssignments()
                ->with('shiftSchedule')
                ->where('effective_from', '<=', $workDateStr)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $workDateStr))
                ->latest('effective_from')
                ->first()
                ?->shiftSchedule;
        }

        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $scheduledMinutes = 480; // default 8 hours

        if ($shift) {
            $workDateStr = $log->work_date instanceof \DateTimeInterface
                ? $log->work_date->format('Y-m-d')
                : (string) $log->work_date;

            // Tardiness: how many minutes late beyond grace period
            $scheduledIn = Carbon::parse($workDateStr . ' ' . $shift->start_time);
            $graceEnd = $scheduledIn->copy()->addMinutes($shift->grace_period_minutes ?? 0);

            if ($timeIn->gt($graceEnd)) {
                $lateMinutes = (int) $graceEnd->diffInMinutes($timeIn);
            }

            // Undertime: how many minutes early departure
            $scheduledOut = Carbon::parse($workDateStr . ' ' . $shift->end_time);

            // Handle night shifts crossing midnight
            if ($shift->is_night_shift && $scheduledOut->lte($scheduledIn)) {
                $scheduledOut->addDay();
            }

            if ($timeOut->lt($scheduledOut)) {
                $undertimeMinutes = (int) $timeOut->diffInMinutes($scheduledOut);
            }

            // Scheduled net minutes (without break)
            $scheduledMinutes = $shift->netWorkingMinutes();
        }

        // Deduct break from total
        $breakMinutes = $shift?->break_minutes ?? 60;
        $workedMinutes = max(0, $totalMinutes - $breakMinutes);

        // Overtime: only if approved OT request exists and worked beyond schedule
        $overtimeMinutes = 0;
        if ($log->overtime_request_id && $workedMinutes > $scheduledMinutes) {
            $overtimeMinutes = $workedMinutes - $scheduledMinutes;
        }

        // Night differential
        $nightDiffMinutes = $this->computeNightDiffMinutes($timeIn, $timeOut);

        // Derive status
        $status = $this->deriveStatus(
            $lateMinutes,
            $undertimeMinutes,
            $log->time_in_within_geofence,
        );

        return [
            'worked_minutes' => $workedMinutes,
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'night_diff_minutes' => $nightDiffMinutes,
            'is_present' => true,
            'is_absent' => false,
            'is_processed' => true,
            'processed_at' => now(),
            'attendance_status' => $status->value,
        ];
    }

    /**
     * Compute minutes of overlap between the work period and the night
     * differential window (from NightShiftConfig).
     */
    private function computeNightDiffMinutes(Carbon $timeIn, Carbon $timeOut): int
    {
        $workDateStr = $timeIn->format('Y-m-d');

        $config = NightShiftConfig::where('effective_date', '<=', $workDateStr)
            ->orderByDesc('effective_date')
            ->first();

        if (! $config) {
            // Default PH night diff: 22:00 – 06:00
            $nightStart = Carbon::parse($workDateStr . ' 22:00:00');
            $nightEnd = Carbon::parse($workDateStr . ' 06:00:00')->addDay();
        } else {
            $nightStart = Carbon::parse($workDateStr . ' ' . $config->night_start_time);
            $nightEnd = Carbon::parse($workDateStr . ' ' . $config->night_end_time);
        }

        // Night window crosses midnight
        if ($nightEnd->lte($nightStart)) {
            $nightEnd->addDay();
        }

        // Compute overlap between [timeIn, timeOut] and [nightStart, nightEnd]
        $overlapStart = $timeIn->max($nightStart);
        $overlapEnd = $timeOut->min($nightEnd);

        if ($overlapEnd->lte($overlapStart)) {
            return 0;
        }

        return (int) $overlapStart->diffInMinutes($overlapEnd);
    }

    private function deriveStatus(
        int $lateMinutes,
        int $undertimeMinutes,
        ?bool $withinGeofence,
    ): AttendanceStatus {
        if ($withinGeofence === false) {
            return AttendanceStatus::OutOfOffice;
        }

        if ($lateMinutes > 0 && $undertimeMinutes > 0) {
            return AttendanceStatus::LateAndUndertime;
        }

        if ($lateMinutes > 0) {
            return AttendanceStatus::Late;
        }

        if ($undertimeMinutes > 0) {
            return AttendanceStatus::Undertime;
        }

        return AttendanceStatus::Present;
    }


}
