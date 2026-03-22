<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\HR\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample Attendance Seeder for January and February 2026.
 * Creates attendance logs for active employees.
 */
class SampleAttendanceJanFeb2026Seeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding sample attendance for Jan-Feb 2026...');

        // Get employees
        $employees = Employee::where('employment_status', 'active')
            ->limit(15)
            ->get();

        if ($employees->isEmpty()) {
            $this->command->warn('No active employees found.');
            return;
        }

        // Work days for Jan and Feb 2026
        $workDays = [
            // Jan 2026 (excluding weekends and New Year)
            '2026-01-02', '2026-01-05', '2026-01-06', '2026-01-07', '2026-01-08', '2026-01-09',
            '2026-01-12', '2026-01-13', '2026-01-14', '2026-01-15', '2026-01-16',
            '2026-01-19', '2026-01-20', '2026-01-21', '2026-01-22', '2026-01-23',
            '2026-01-26', '2026-01-27', '2026-01-28', '2026-01-29', '2026-01-30',
            // Feb 2026
            '2026-02-02', '2026-02-03', '2026-02-04', '2026-02-05', '2026-02-06',
            '2026-02-09', '2026-02-10', '2026-02-11', '2026-02-12', '2026-02-13',
            '2026-02-16', '2026-02-17', '2026-02-18', '2026-02-19', '2026-02-20',
            '2026-02-23', '2026-02-24', '2026-02-25', '2026-02-26', '2026-02-27',
        ];

        $createdCount = 0;

        foreach ($employees as $employee) {
            foreach ($workDays as $date) {
                // Skip some days randomly for variety (absences)
                if (rand(1, 20) === 1) {
                    continue; // Absent
                }

                // Normal shift: 8:00 AM - 5:00 PM
                $timeInStr = '08:' . str_pad((string) rand(0, 15), 2, '0', STR_PAD_LEFT);
                $timeOutStr = '17:' . str_pad((string) rand(0, 30), 2, '0', STR_PAD_LEFT);

                // Some employees have overtime
                $hasOvertime = rand(1, 5) === 1;
                $timeOutStr = $hasOvertime ? '17:00' : $timeOutStr;

                // Some days have undertime
                $hasUndertime = rand(1, 10) === 1;
                if ($hasUndertime) {
                    $timeOutStr = '16:' . str_pad((string) rand(0, 30), 2, '0', STR_PAD_LEFT);
                }

                // Format as timestamp
                $timeIn = $date . ' ' . $timeInStr . ':00';
                $timeOut = $date . ' ' . $timeOutStr . ':00';

                $lateMinutes = $this->calculateLateMinutes($timeInStr);
                $undertimeMinutes = $hasUndertime ? rand(30, 60) : 0;
                $overtimeMinutes = $hasOvertime ? rand(60, 180) : 0;

                // Calculate worked minutes (8 hours = 480 mins - late - undertime + overtime)
                $workedMinutes = max(0, 480 - $lateMinutes - $undertimeMinutes + $overtimeMinutes);

                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'work_date' => $date,
                    'time_in' => $timeIn,
                    'time_out' => $timeOut,
                    'is_present' => true,
                    'is_absent' => false,
                    'is_rest_day' => false,
                    'is_holiday' => $date === '2026-01-02', // New Year holiday
                    'holiday_type' => $date === '2026-01-02' ? 'regular' : null,
                    'late_minutes' => $lateMinutes,
                    'undertime_minutes' => $undertimeMinutes,
                    'overtime_minutes' => $overtimeMinutes,
                    'worked_minutes' => $workedMinutes,
                    'night_diff_minutes' => 0,
                    'is_processed' => true,
                    'source' => 'manual',
                    'remarks' => $hasOvertime ? 'Overtime approved' : null,
                ]);

                $createdCount++;
            }
        }

        $this->command->info("✓ Created {$createdCount} attendance logs for {$employees->count()} employees.");
    }

    private function calculateLateMinutes(string $timeInStr): int
    {
        $hour = (int) substr($timeInStr, 0, 2);
        $minute = (int) substr($timeInStr, 3, 2);

        if ($hour < 8) {
            return 0;
        }

        if ($hour === 8 && $minute <= 0) {
            return 0;
        }

        return (($hour - 8) * 60) + $minute;
    }
}
