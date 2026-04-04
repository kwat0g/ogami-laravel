<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Enums\AttendanceStatus;
use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\HR\Models\Employee;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Core time-in / time-out service for GPS-based attendance.
 *
 * Handles the employee-facing clock-in/out flow:
 *  1. Validate shift exists for today
 *  2. Validate geofence
 *  3. Create/update AttendanceLog with geo data
 *  4. Compute all derived fields on time-out
 *  5. Generate absent records for end-of-day job
 *  6. Auto time-out for forgotten clock-outs
 */
final class AttendanceTimeService implements ServiceContract
{
    public function __construct(
        private readonly GeoFenceService $geoFence,
        private readonly TimeComputationService $timeComputation,
        private readonly ShiftResolverService $shiftResolver,
    ) {}

    /**
     * Record Time In for an employee.
     *
     * @param  array<string, mixed>  $deviceInfo  Browser/OS/IP metadata
     *
     * @throws DomainException
     */
    public function timeIn(
        Employee $employee,
        float $latitude,
        float $longitude,
        float $accuracyMeters,
        array $deviceInfo,
        ?string $overrideReason = null,
    ): AttendanceLog {
        return DB::transaction(function () use (
            $employee, $latitude, $longitude, $accuracyMeters, $deviceInfo, $overrideReason
        ) {
            $today = today()->toDateString();

            // Block duplicate time-in on same day
            $existing = AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $today)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->time_in !== null) {
                throw new DomainException(
                    'You have already timed in today. Use correction request if needed.',
                    'ALREADY_TIMED_IN',
                    422,
                );
            }

            // Resolve shift for today
            $shift = $this->shiftResolver->resolve($employee, $today);

            if (! $shift) {
                throw new DomainException(
                    'No shift schedule assigned for today. Contact HR.',
                    'NO_SHIFT_ASSIGNED',
                    422,
                );
            }

            // Validate geofence — enforcement depends on system_settings
            $geofenceMode = $this->geoFence->getGeofenceMode();
            $geo = $this->geoFence->validateLocation($employee, $latitude, $longitude, now());

            if ($geofenceMode !== 'disabled') {
                if (! $geo['location']) {
                    if ($geofenceMode === 'strict') {
                        throw new DomainException(
                            'No work location is assigned to your profile. Clock-in is blocked. Please contact HR.',
                            'NO_WORK_LOCATION',
                            422,
                        );
                    }
                } elseif (! $geo['within']) {
                    if ($geofenceMode === 'strict') {
                        // Strict mode: block entirely — no override possible
                        throw new DomainException(
                            "You are {$geo['distance_meters']}m from your assigned work location" .
                            " ({$geo['location']->name})" .
                            '. Clock-in is only allowed within the geofence. Contact HR if this is an error.',
                            'OUTSIDE_GEOFENCE_BLOCKED',
                            422,
                            ['distance_meters' => $geo['distance_meters'], 'location' => $geo['location']->name],
                        );
                    }

                }

                // Check GPS accuracy explicitly. If it's terrible, treat it as a warning or block
                if ($accuracyMeters > 500) {
                    if ($geofenceMode === 'strict') {
                        throw new DomainException(
                            "Location accuracy is too low (±" . round($accuracyMeters) . "m) to reliably verify geofence. Please ensure your GPS is stable to clock in.",
                            'LOCATION_INACCURATE_BLOCKED',
                            422,
                        );
                    }
                }
            }

            $log = AttendanceLog::updateOrCreate(
                ['employee_id' => $employee->id, 'work_date' => $today],
                [
                    'source' => 'web_clock',
                    'work_location_id' => $geo['location']?->id,
                    'time_in' => now(),
                    'time_in_latitude' => $latitude,
                    'time_in_longitude' => $longitude,
                    'time_in_accuracy_meters' => $accuracyMeters,
                    'time_in_distance_meters' => $geo['distance_meters'],
                    'time_in_within_geofence' => $geo['within'],
                    'time_in_device_info' => $deviceInfo,
                    'time_in_override_reason' => $overrideReason,
                    'attendance_status' => AttendanceStatus::Pending->value,
                    'is_flagged' => ! $geo['within'] || (bool) $overrideReason,
                    'flag_reason' => ! $geo['within']
                        ? "Timed in {$geo['distance_meters']}m from work location."
                        : ($overrideReason ? "Clock-in override: {$overrideReason}" : null),
                ],
            );

            return $log;
        });
    }

    /**
     * Record Time Out and compute all derived time fields.
     *
     * @throws DomainException
     */
    public function timeOut(
        Employee $employee,
        float $latitude,
        float $longitude,
        float $accuracyMeters,
        array $deviceInfo,
        ?string $overrideReason = null,
    ): AttendanceLog {
        return DB::transaction(function () use (
            $employee, $latitude, $longitude, $accuracyMeters, $deviceInfo, $overrideReason
        ) {
            $today = today()->toDateString();

            $log = AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $today)
                ->lockForUpdate()
                ->first();

            if (! $log || $log->time_in === null) {
                throw new DomainException(
                    'Cannot time out without timing in first.',
                    'NOT_TIMED_IN',
                    422,
                );
            }

            if ($log->time_out !== null) {
                throw new DomainException(
                    'You have already timed out today.',
                    'ALREADY_TIMED_OUT',
                    422,
                );
            }

            $geo = $this->geoFence->validateLocation($employee, $latitude, $longitude, now());

            $log->update([
                'time_out' => now(),
                'time_out_latitude' => $latitude,
                'time_out_longitude' => $longitude,
                'time_out_accuracy_meters' => $accuracyMeters,
                'time_out_distance_meters' => $geo['distance_meters'],
                'time_out_within_geofence' => $geo['within'],
                'time_out_device_info' => $deviceInfo,
                'time_out_override_reason' => $overrideReason,
            ]);

            // Compute all derived time fields
            $computed = $this->timeComputation->compute($log->fresh());
            $log->update($computed);

            return $log->fresh();
        });
    }

    /**
     * Auto-generate absent records for employees who did not time in.
     * Runs as a scheduled job at end of each working day.
     * Idempotent — skips if a record already exists for the date.
     */
    public function generateAbsentRecords(Carbon $date): int
    {
        $dateStr = $date->toDateString();
        $count = 0;

        // Get all active employees with a shift assigned for this date
        $employees = Employee::where('employment_status', 'active')
            ->get();

        foreach ($employees as $employee) {
            // Skip if a record already exists for this date
            $existing = AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $dateStr)
                ->exists();

            if ($existing) {
                continue;
            }

            // Skip if rest day
            if ($this->shiftResolver->isRestDay($employee, $dateStr)) {
                continue;
            }

            // Skip if holiday
            $holiday = $this->shiftResolver->isHoliday($dateStr);
            if ($holiday) {
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'work_date' => $dateStr,
                    'source' => 'system',
                    'is_present' => false,
                    'is_absent' => false,
                    'is_holiday' => true,
                    'holiday_type' => strtolower($holiday->type),
                    'attendance_status' => AttendanceStatus::Holiday->value,
                ]);
                $count++;

                continue;
            }

            // Skip if no shift assigned
            $shift = $this->shiftResolver->resolve($employee, $dateStr);
            if (! $shift) {
                continue;
            }

            // Check approved leave covering this date
            $hasApprovedLeave = DB::table('leave_requests')
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->where('date_from', '<=', $dateStr)
                ->where('date_to', '>=', $dateStr)
                ->exists();

            if ($hasApprovedLeave) {
                // Leave listener should have already created the record,
                // but if not, skip — don't mark as absent
                continue;
            }

            // Mark as absent
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'work_date' => $dateStr,
                'source' => 'system',
                'is_present' => false,
                'is_absent' => true,
                'attendance_status' => AttendanceStatus::Absent->value,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Auto time-out employees who forgot to clock out.
     * Sets time_out to the provided cutoff time and flags the record.
     */
    public function autoTimeOut(Carbon $cutoffTime): int
    {
        $count = 0;

        $openLogs = AttendanceLog::whereNotNull('time_in')
            ->whereNull('time_out')
            ->where('source', 'web_clock')
            ->where('work_date', today()->toDateString())
            ->get();

        foreach ($openLogs as $log) {
            $log->update([
                'time_out' => $cutoffTime,
                'is_flagged' => true,
                'flag_reason' => trim(
                    ($log->flag_reason ? $log->flag_reason . ' | ' : '') .
                    'Auto timed-out at ' . $cutoffTime->format('H:i') . ' (forgot to clock out).'
                ),
            ]);

            // Compute derived fields
            $computed = $this->timeComputation->compute($log->fresh());
            $log->update($computed);

            $count++;
        }

        return $count;
    }
}
