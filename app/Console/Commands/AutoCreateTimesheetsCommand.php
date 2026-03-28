<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Attendance\Models\TimesheetApproval;
use App\Domains\HR\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-Create Timesheets at Period End — Automation A10.
 *
 * At the end of each pay period (semi-monthly: 1-15 and 16-end),
 * aggregates attendance logs into a TimesheetApproval record for
 * supervisor approval. Wires the previously orphaned TimesheetApproval model.
 *
 * Designed to run daily via scheduler — only creates timesheets on
 * period boundaries (16th and 1st of each month).
 */
final class AutoCreateTimesheetsCommand extends Command
{
    protected $signature = 'attendance:auto-timesheets {--force : Create even if not period boundary}';

    protected $description = 'Auto-create timesheet approval records from attendance logs at pay period end';

    public function handle(): int
    {
        $today = now();
        $dayOfMonth = $today->day;

        // Semi-monthly periods: 1-15 and 16-end
        // Timesheets created on the 16th (for 1-15) and 1st (for 16-end of prev month)
        $isPeriodBoundary = in_array($dayOfMonth, [1, 16], true);

        if (! $isPeriodBoundary && ! $this->option('force')) {
            $this->info("Not a period boundary (day {$dayOfMonth}). Use --force to override.");

            return self::SUCCESS;
        }

        // Determine the period to summarize
        if ($dayOfMonth <= 15) {
            // Create timesheet for previous month 16-end
            $periodStart = $today->copy()->subMonth()->startOfMonth()->addDays(15); // 16th
            $periodEnd = $today->copy()->subMonth()->endOfMonth();
        } else {
            // Create timesheet for current month 1-15
            $periodStart = $today->copy()->startOfMonth();
            $periodEnd = $today->copy()->startOfMonth()->addDays(14); // 15th
        }

        $this->info("Creating timesheets for period: {$periodStart->toDateString()} to {$periodEnd->toDateString()}");

        $activeEmployees = Employee::where('employment_status', 'active')
            ->whereNull('deleted_at')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($activeEmployees as $employee) {
            // Check if timesheet already exists for this employee + period
            $exists = TimesheetApproval::where('employee_id', $employee->id)
                ->where('period_start', $periodStart->toDateString())
                ->where('period_end', $periodEnd->toDateString())
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            // Aggregate attendance data for the period
            $attendance = DB::table('attendance_logs')
                ->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->selectRaw("
                    COUNT(*) as total_days,
                    COUNT(*) FILTER (WHERE is_present = true) as days_present,
                    COUNT(*) FILTER (WHERE is_absent = true) as days_absent,
                    COALESCE(SUM(late_minutes), 0) as total_late_minutes,
                    COALESCE(SUM(undertime_minutes), 0) as total_undertime_minutes,
                    COALESCE(SUM(overtime_minutes), 0) as total_overtime_minutes,
                    COALESCE(SUM(worked_minutes), 0) as total_worked_minutes
                ")
                ->first();

            try {
                TimesheetApproval::create([
                    'employee_id' => $employee->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'days_present' => (int) ($attendance->days_present ?? 0),
                    'days_absent' => (int) ($attendance->days_absent ?? 0),
                    'total_late_minutes' => (int) ($attendance->total_late_minutes ?? 0),
                    'total_undertime_minutes' => (int) ($attendance->total_undertime_minutes ?? 0),
                    'total_overtime_minutes' => (int) ($attendance->total_overtime_minutes ?? 0),
                    'total_worked_minutes' => (int) ($attendance->total_worked_minutes ?? 0),
                    'status' => 'draft',
                ]);

                $created++;
            } catch (\Throwable $e) {
                Log::warning('[Timesheet] Failed to create timesheet', [
                    'employee_id' => $employee->id,
                    'period' => "{$periodStart->toDateString()} to {$periodEnd->toDateString()}",
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Timesheets created: {$created}, skipped (already exists): {$skipped}");

        return self::SUCCESS;
    }
}
