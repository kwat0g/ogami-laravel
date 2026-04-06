<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\HR\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

/**
 * Attendance Seeder: January 1 – Today (All Active Employees).
 *
 * Generates realistic daily attendance for every active employee.
 * Weekends are skipped. Includes realistic late arrivals, undertimes,
 * overtimes, and random absences (~5% rate).
 */
class SampleAttendanceJanFeb2026Seeder extends Seeder
{
    /** Philippine regular holidays in 2026 (date => name) */
    private const HOLIDAYS = [
        '2026-01-01' => 'regular',   // New Year's Day
        '2026-02-25' => 'regular',   // EDSA Revolution
        '2026-04-01' => 'regular',   // Araw ng Kagitingan (moved)
        '2026-04-02' => 'special_non_working',   // Maundy Thursday
        '2026-04-03' => 'regular',   // Good Friday
        '2026-04-04' => 'special_non_working',   // Black Saturday
        '2026-05-01' => 'regular',   // Labor Day
        '2026-06-12' => 'regular',   // Independence Day
    ];

    public function run(): void
    {
        $this->command->info('Seeding attendance: Jan 1, 2026 → today (all employees)...');

        $employees = Employee::where('employment_status', 'active')->get();

        if ($employees->isEmpty()) {
            $this->command->warn('No active employees found.');
            return;
        }

        // Build workday list: Jan 1 to today, skip weekends
        $start = Carbon::parse('2026-01-01');
        $end   = Carbon::today()->min(Carbon::parse('2026-04-06'));
        $period = CarbonPeriod::create($start, $end);

        $workDays = [];
        foreach ($period as $day) {
            if ($day->isWeekend()) {
                continue;
            }
            $workDays[] = $day->toDateString();
        }

        $total = 0;
        $batch = [];

        foreach ($employees as $employee) {
            foreach ($workDays as $date) {
                // ~5% absence rate
                if (rand(1, 20) === 1) {
                    continue;
                }

                $isHoliday   = isset(self::HOLIDAYS[$date]);
                $holidayType = self::HOLIDAYS[$date] ?? null;

                // Randomise arrival: most on time, some late
                $lateRoll    = rand(1, 10);
                $arriveHour  = 8;
                $arriveMin   = $lateRoll <= 7 ? rand(0, 5) : rand(6, 30);

                // Randomise departure
                $hasOvertime  = rand(1, 8) === 1;
                $hasUndertime = !$hasOvertime && rand(1, 12) === 1;

                if ($hasOvertime) {
                    $departHour = rand(18, 20);
                    $departMin  = rand(0, 59);
                } elseif ($hasUndertime) {
                    $departHour = 16;
                    $departMin  = rand(0, 45);
                } else {
                    $departHour = 17;
                    $departMin  = rand(0, 30);
                }

                $timeIn  = sprintf('%s %02d:%02d:00', $date, $arriveHour, $arriveMin);
                $timeOut = sprintf('%s %02d:%02d:00', $date, $departHour, $departMin);

                $lateMinutes      = $arriveMin > 0 ? $arriveMin : 0;
                $undertimeMinutes = $hasUndertime ? (60 - $departMin) : 0;
                $overtimeMinutes  = $hasOvertime ? (($departHour - 17) * 60 + $departMin) : 0;
                $workedMinutes    = max(0, 480 - $lateMinutes - $undertimeMinutes + $overtimeMinutes);

                $batch[] = [
                    'employee_id'       => $employee->id,
                    'work_date'         => $date,
                    'time_in'           => $timeIn,
                    'time_out'          => $timeOut,
                    'is_present'        => true,
                    'is_absent'         => false,
                    'is_rest_day'       => false,
                    'is_holiday'        => $isHoliday,
                    'holiday_type'      => $holidayType,
                    'late_minutes'      => $lateMinutes,
                    'undertime_minutes' => $undertimeMinutes,
                    'overtime_minutes'  => $overtimeMinutes,
                    'worked_minutes'    => $workedMinutes,
                    'night_diff_minutes' => 0,
                    'is_processed'      => true,
                    'source'            => 'biometric',
                    'remarks'           => $hasOvertime ? 'Overtime approved' : null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
                $total++;

                // Bulk insert every 500 records for performance
                if (count($batch) >= 500) {
                    AttendanceLog::insert($batch);
                    $batch = [];
                }
            }
        }

        // Insert remaining
        if (! empty($batch)) {
            AttendanceLog::insert($batch);
        }

        $this->command->info("✓ Created {$total} attendance logs for {$employees->count()} employees ({$start->toDateString()} → {$end->toDateString()}).");
    }
}
