<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\Models\OvertimeRequest;
use App\Domains\HR\Models\Employee;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Core attendance rule engine — ATT-001 through ATT-010.
 *
 * ATT-001: Each employee may have at most one log per work_date (UNIQUE at DB).
 * ATT-002: Late arrival = actual time_in > shift start + grace_period_minutes.
 * ATT-003: Undertime = time_out < shift end (without approved justification).
 * ATT-004: Absent = present is false AND rest_day is false AND holiday is false.
 * ATT-005: Overtime credited ONLY when an approved OvertimeRequest exists for that date.
 * ATT-006: Night differential applies when worked hours fall between 22:00–06:00.
 * ATT-007: Holiday pay multiplier applied by Payroll domain (not here).
 * ATT-010: Processed logs are immutable unless reopened by HR (audit trail via Auditable).
 *
 * This service operates on already-parsed time data (from biometric or CSV import).
 * It does NOT own the import pipeline — that belongs to AttendanceImportService.
 */
final class AttendanceProcessingService implements ServiceContract
{
    /**
     * Create or update an attendance log for a specific employee + work date.
     * Applies all ATT-001–ATT-006 rules automatically.
     *
     * @param  array<string, mixed>  $raw  { time_in, time_out, source, ... }
     *
     * @throws DomainException
     */
    public function processLog(Employee $employee, string $workDate, array $raw): AttendanceLog
    {
        return DB::transaction(function () use ($employee, $workDate, $raw): AttendanceLog {
            /** @var \App\Domains\Attendance\Models\EmployeeShiftAssignment|null $shiftAssignment */
            $shiftAssignment = $employee->shiftAssignments()
                ->with('shiftSchedule')
                ->where('effective_from', '<=', $workDate)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $workDate))
                ->latest('effective_from')
                ->first();

            $shift = $shiftAssignment?->shiftSchedule;

            $timeIn = $raw['time_in'] ?? null;
            $timeOut = $raw['time_out'] ?? null;

            // ATT-001: worked_minutes
            $workedMinutes = 0;
            if ($timeIn && $timeOut) {
                $in = Carbon::parse("{$workDate} {$timeIn}");
                $out = Carbon::parse("{$workDate} {$timeOut}");
                if ($out->lt($in)) {
                    $out->addDay(); // overnight
                }
                $workedMinutes = (int) $in->diffInMinutes($out);
                // Deduct break
                if ($shift) {
                    $workedMinutes = max(0, $workedMinutes - $shift->break_minutes);
                }
            }

            // ATT-002: late_minutes
            $lateMinutes = 0;
            if ($shift && $timeIn) {
                $scheduledIn = Carbon::parse("{$workDate} {$shift->start_time}");
                $actualIn = Carbon::parse("{$workDate} {$timeIn}");
                $diffMins = $scheduledIn->diffInMinutes($actualIn, false);
                $gracePeriod = $shift->grace_period_minutes ?? 0;
                if ($diffMins > $gracePeriod) {
                    $lateMinutes = max(0, (int) ($diffMins - $gracePeriod));
                }
            }

            // ATT-003: undertime_minutes
            $undertimeMinutes = 0;
            if ($shift && $timeOut) {
                $scheduledOut = Carbon::parse("{$workDate} {$shift->end_time}");
                $actualOut = Carbon::parse("{$workDate} {$timeOut}");
                if ($scheduledOut->gt($actualOut)) {
                    $undertimeMinutes = (int) $scheduledOut->diffInMinutes($actualOut);
                }
            }

            // ATT-005: overtime — only if an approved OvertimeRequest exists
            $approvedOt = OvertimeRequest::where('employee_id', $employee->id)
                ->where('work_date', $workDate)
                ->where('status', 'approved')
                ->first();
            $overtimeMinutes = $approvedOt ? ($approvedOt->approved_minutes ?? 0) : 0;

            // ATT-006: night differential
            $nightDiffMinutes = 0;
            $isNightDiff = false;
            if ($timeIn && $timeOut) {
                $nightDiffMinutes = $this->calculateNightDiffMinutes(
                    Carbon::parse("{$workDate} {$timeIn}"),
                    Carbon::parse("{$workDate} {$timeOut}"),
                );
                $isNightDiff = $nightDiffMinutes > 0;
            }

            $isPresent = ($workedMinutes > 0);
            $isAbsent = (! $isPresent)
                && empty($raw['is_rest_day'])
                && empty($raw['is_holiday']);

            $existing = AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $workDate)
                ->first();

            $attributes = [
                'source' => $raw['source'] ?? 'manual',
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'worked_minutes' => $workedMinutes,
                'late_minutes' => $lateMinutes,
                'undertime_minutes' => $undertimeMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'is_present' => $isPresent,
                'is_absent' => $isAbsent,
                'is_rest_day' => (bool) ($raw['is_rest_day'] ?? false),
                'is_holiday' => (bool) ($raw['is_holiday'] ?? false),
                'holiday_type' => $raw['holiday_type'] ?? null,
                'is_night_diff' => $isNightDiff,
                'night_diff_minutes' => $nightDiffMinutes,
                'remarks' => $raw['remarks'] ?? null,
                'import_batch_id' => $raw['import_batch_id'] ?? null,
                'processed_by' => $raw['processed_by'] ?? null,
            ];

            if ($existing) {
                $existing->fill($attributes)->save();

                return $existing->fresh();
            }

            return AttendanceLog::create(
                array_merge(['employee_id' => $employee->id, 'work_date' => $workDate], $attributes)
            );
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Calculate how many minutes of the given time-range fall within 22:00–06:00.
     */
    private function calculateNightDiffMinutes(Carbon $timeIn, Carbon $timeOut): int
    {
        if ($timeOut->lt($timeIn)) {
            $timeOut->addDay();
        }

        $nightStart = $timeIn->copy()->setTime(22, 0);
        $nightEnd = $timeIn->copy()->addDay()->setTime(6, 0);

        // Shift window to encompass the time range
        if ($timeIn->hour < 6) {
            $nightStart->subDay();
            $nightEnd->subDay();
        }

        $overlapStart = $timeIn->max($nightStart);
        $overlapEnd = $timeOut->min($nightEnd);

        if ($overlapEnd->lte($overlapStart)) {
            return 0;
        }

        return (int) $overlapStart->diffInMinutes($overlapEnd);
    }
}
