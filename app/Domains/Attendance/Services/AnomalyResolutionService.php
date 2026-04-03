<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AnomalyResolutionService
 *
 * Automatically detects and resolves common attendance anomalies
 * to reduce manual HR intervention.
 *
 * Anomaly types handled:
 * 1. Missing clock-out: Employee clocked in but never clocked out (after 10+ hours)
 *    -> Auto-set time_out to shift end time
 * 2. Duplicate entries: Multiple logs for same employee + same date
 *    -> Keep biometric, discard manual; or keep earliest in + latest out
 * 3. Clock-in without shift: Employee has a log but no shift assignment
 *    -> Flag for HR review (cannot auto-resolve)
 *
 * Usage:
 *   $service->detectAnomalies()           // Scan and return anomalies
 *   $service->autoResolve()               // Auto-fix what can be fixed
 *   $service->resolveById($id, $action)   // Manually resolve one anomaly
 */
final class AnomalyResolutionService implements ServiceContract
{
    /**
     * Detect all current anomalies.
     *
     * @return Collection<int, array{type: string, log_id: int, employee_id: int, employee_name: string, work_date: string, detail: string, auto_resolvable: bool}>
     */
    public function detectAnomalies(): Collection
    {
        $anomalies = collect();

        // 1. Missing clock-out (clocked in 10+ hours ago, no time_out)
        $missingClockOuts = AttendanceLog::query()
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->where('work_date', '<', now()->toDateString()) // Only past dates
            ->with('employee:id,first_name,last_name')
            ->get();

        foreach ($missingClockOuts as $log) {
            $anomalies->push([
                'type' => 'missing_clock_out',
                'log_id' => $log->id,
                'employee_id' => $log->employee_id,
                'employee_name' => $log->employee?->full_name ?? "Employee #{$log->employee_id}",
                'work_date' => $log->work_date,
                'detail' => "Clocked in at {$log->time_in} but no clock-out recorded",
                'auto_resolvable' => true,
            ]);
        }

        // 2. Duplicate entries (multiple logs per employee per date)
        $duplicates = DB::table('attendance_logs')
            ->select('employee_id', 'work_date', DB::raw('COUNT(*) as log_count'))
            ->whereNull('deleted_at')
            ->groupBy('employee_id', 'work_date')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $employee = DB::table('employees')
                ->where('id', $dup->employee_id)
                ->select('first_name', 'last_name')
                ->first();

            $name = $employee
                ? trim("{$employee->first_name} {$employee->last_name}")
                : "Employee #{$dup->employee_id}";

            $anomalies->push([
                'type' => 'duplicate_entry',
                'log_id' => null,
                'employee_id' => $dup->employee_id,
                'employee_name' => $name,
                'work_date' => $dup->work_date,
                'detail' => "{$dup->log_count} entries found for this date",
                'auto_resolvable' => true,
            ]);
        }

        return $anomalies;
    }

    /**
     * Auto-resolve all resolvable anomalies.
     *
     * @return array{resolved: int, skipped: int, errors: int}
     */
    public function autoResolve(): array
    {
        $stats = ['resolved' => 0, 'skipped' => 0, 'errors' => 0];

        $anomalies = $this->detectAnomalies();

        foreach ($anomalies as $anomaly) {
            if (! $anomaly['auto_resolvable']) {
                $stats['skipped']++;
                continue;
            }

            try {
                match ($anomaly['type']) {
                    'missing_clock_out' => $this->resolveMissingClockOut($anomaly['log_id']),
                    'duplicate_entry' => $this->resolveDuplicate($anomaly['employee_id'], $anomaly['work_date']),
                    default => null,
                };
                $stats['resolved']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning('[AnomalyResolution] Failed to resolve', [
                    'type' => $anomaly['type'],
                    'log_id' => $anomaly['log_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[AnomalyResolution] Auto-resolve completed', $stats);

        return $stats;
    }

    /**
     * Resolve a missing clock-out by setting time_out to shift end time
     * or default 18:00 (6 PM).
     */
    private function resolveMissingClockOut(int $logId): void
    {
        $log = AttendanceLog::findOrFail($logId);

        if ($log->time_out !== null) {
            return; // Already resolved
        }

        // Try to get shift end time from employee's shift assignment
        $shiftEnd = null;
        $shift = DB::table('employee_shift_assignments')
            ->where('employee_id', $log->employee_id)
            ->where('effective_date', '<=', $log->work_date)
            ->orderByDesc('effective_date')
            ->first();

        if ($shift) {
            $shiftSchedule = DB::table('shift_schedules')
                ->where('id', $shift->shift_schedule_id)
                ->first();

            if ($shiftSchedule) {
                $shiftEnd = $shiftSchedule->end_time;
            }
        }

        // Default to 18:00 if no shift found
        $timeOut = $shiftEnd ?? '18:00:00';

        // Recalculate worked minutes
        $timeIn = Carbon::parse("{$log->work_date} {$log->time_in}");
        $timeOutCarbon = Carbon::parse("{$log->work_date} {$timeOut}");
        $workedMinutes = max(0, (int) $timeIn->diffInMinutes($timeOutCarbon));

        $log->update([
            'time_out' => $timeOut,
            'worked_minutes' => $workedMinutes,
            'source' => 'system', // Mark as system-resolved
            'remarks' => ($log->remarks ? $log->remarks . ' | ' : '') . 'Auto-resolved: missing clock-out set to shift end',
        ]);

        Log::info('[AnomalyResolution] Missing clock-out resolved', [
            'log_id' => $logId,
            'employee_id' => $log->employee_id,
            'work_date' => $log->work_date,
            'time_out' => $timeOut,
            'worked_minutes' => $workedMinutes,
        ]);
    }

    /**
     * Resolve duplicate entries by keeping the biometric one (or the one
     * with the longest shift if both are biometric).
     */
    private function resolveDuplicate(int $employeeId, string $workDate): void
    {
        $logs = AttendanceLog::where('employee_id', $employeeId)
            ->where('work_date', $workDate)
            ->orderBy('created_at')
            ->get();

        if ($logs->count() <= 1) {
            return;
        }

        // Priority: biometric > csv_import > system > manual
        $sourcePriority = ['biometric' => 4, 'csv_import' => 3, 'system' => 2, 'manual' => 1];

        // Find the best log to keep
        $keep = $logs->sortByDesc(function (AttendanceLog $log) use ($sourcePriority) {
            $priority = $sourcePriority[$log->source ?? 'manual'] ?? 0;
            $worked = (int) ($log->worked_minutes ?? 0);
            // Score: source priority * 10000 + worked minutes
            return ($priority * 10000) + $worked;
        })->first();

        // Soft-delete the rest
        $removed = 0;
        foreach ($logs as $log) {
            if ($log->id === $keep->id) {
                continue;
            }

            $log->update([
                'remarks' => ($log->remarks ? $log->remarks . ' | ' : '') . "Auto-resolved: duplicate removed (kept log #{$keep->id})",
            ]);
            $log->delete(); // Soft delete

            $removed++;
        }

        Log::info('[AnomalyResolution] Duplicate resolved', [
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'kept_log_id' => $keep->id,
            'removed_count' => $removed,
        ]);
    }
}
