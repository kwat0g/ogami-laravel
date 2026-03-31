<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\HR\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;

final class MarkAbsencesCommand extends Command
{
    /** @var string */
    protected $signature = 'attendance:mark-absences {--date= : The date to process (Y-m-d), defaults to today}';

    /** @var string */
    protected $description = 'Marks active employees absent if they have no time-in record for the day.';

    public function handle(): int
    {
        $dateStr = $this->option('date') ? (string) $this->option('date') : Carbon::today()->toDateString();
        $date = Carbon::parse($dateStr);
        $dayOfWeekIso = (int) $date->format('N'); // 1 (Mon) - 7 (Sun)

        $this->info("Marking absences for {$dateStr}...");

        $employees = Employee::where('status', 'active')
            ->with(['shiftAssignments' => function ($q) use ($dateStr) {
                $q->where('effective_from', '<=', $dateStr)
                  ->where(function ($sub) use ($dateStr) {
                      $sub->whereNull('effective_to')->orWhere('effective_to', '>=', $dateStr);
                  })
                  ->with('shiftSchedule');
            }])->get();

        $absentCount = 0;

        foreach ($employees as $employee) {
            $assignment = $employee->shiftAssignments->first();
            
            if (! $assignment || ! $assignment->shiftSchedule) {
                continue;
            }
            
            $shift = $assignment->shiftSchedule;
            
            // Check if it is a work day
            $workDays = $shift->work_days_array ?? [];
            if (! in_array($dayOfWeekIso, $workDays, true)) {
                continue;
            }

            // Check if there is already an attendance log for today
            $logExists = AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $dateStr)
                ->exists();

            if (! $logExists) {
                // Auto-mark as absent
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'work_date' => $dateStr,
                    'source' => 'system',
                    'is_present' => false,
                    'is_absent' => true,
                    'is_rest_day' => false,
                    'is_holiday' => false, // Will depend on holiday check, but simple for now
                    'worked_minutes' => 0,
                    'late_minutes' => 0,
                    'undertime_minutes' => $shift->netWorkingMinutes(),
                    'overtime_minutes' => 0,
                    'night_diff_minutes' => 0,
                    'attendance_status' => 'absent',
                    'remarks' => 'Auto-marked absent by system'
                ]);

                $absentCount++;
            }
        }

        $this->info("Successfully marked {$absentCount} employees as absent.");

        return self::SUCCESS;
    }
}
