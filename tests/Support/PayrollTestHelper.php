<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\Position;
use App\Domains\Payroll\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Console\Kernel;
use Illuminate\Support\Facades\DB;

/**
 * Payroll Test Helper — shared factory utilities for payroll unit/integration tests.
 *
 * All monetary values passed to helpers are in PHP PESOS (float), converted
 * internally to centavos to match the production convention.
 */
final class PayrollTestHelper
{
    /**
     * Seed all payroll rate tables required to run the computation pipeline.
     * Calls only the lightweight rate-table seeders (not HolidayCalendar, etc.).
     */
    public static function seedRateTables(): void
    {
        app(Kernel::class)->call('db:seed', [
            '--class' => 'SssContributionTableSeeder',
        ]);
        app(Kernel::class)->call('db:seed', [
            '--class' => 'PagibigContributionTableSeeder',
        ]);
        app(Kernel::class)->call('db:seed', [
            '--class' => 'PhilhealthPremiumTableSeeder',
        ]);
        app(Kernel::class)->call('db:seed', [
            '--class' => 'TrainTaxBracketSeeder',
        ]);
        app(Kernel::class)->call('db:seed', [
            '--class' => 'MinimumWageRateSeeder',
        ]);
        app(Kernel::class)->call('db:seed', [
            '--class' => 'OvertimeMultiplierSeeder',
        ]);
        app(Kernel::class)->call('db:seed', [
            '--class' => 'SystemSettingsSeeder',
        ]);
        app(Kernel::class)->call('db:seed', [
            '--class' => 'HolidayCalendarSeeder',
        ]);
        app(Kernel::class)->call('db:seed', [
            '--class' => 'LeaveTypeSeeder',
        ]);
    }

    /**
     * Create a minimal Employee record suitable for payroll computation.
     *
     * @param  float  $basicMonthlyPesos  e.g., 25000.00
     */
    /**
     * Normalize test-friendly override keys to actual DB column names.
     * Also silently drops GENERATED ALWAYS AS STORED columns (daily_rate, hourly_rate)
     * so PostgreSQL never receives an explicit value for those columns.
     */
    private static function normalizeOverrides(array $overrides): array
    {
        $keyMap = [
            'hired_at' => 'date_hired',
            'resigned_at' => 'separation_date',
        ];
        // GENERATED ALWAYS AS STORED — must never be inserted
        $generated = ['daily_rate', 'hourly_rate'];

        $normalized = [];
        foreach ($overrides as $k => $v) {
            if (in_array($k, $generated, true)) {
                continue;
            }
            $normalized[$keyMap[$k] ?? $k] = $v;
        }

        return $normalized;
    }

    public static function makeEmployee(float $basicMonthlyPesos = 25_000.00, array $overrides = []): Employee
    {
        $basicCentavos = (int) round($basicMonthlyPesos * 100);

        static $seq = 0;
        $seq++;

        // Idempotent: reuse or create a shared test department/position
        $dept = Department::firstOrCreate(
            ['code' => 'DEPT-TEST'],
            ['name' => 'Test Department', 'is_active' => true]
        );
        $pos = Position::firstOrCreate(
            ['code' => 'POS-TEST'],
            ['title' => 'Test Analyst', 'department_id' => $dept->id, 'is_active' => true]
        );

        return Employee::create(array_merge([
            'employee_code' => 'TEST-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'first_name' => 'Juan',
            'last_name' => 'TestCase',
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
            'civil_status' => 'SINGLE',
            'bir_status' => 'S',
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'employment_type' => 'regular',
            'employment_status' => 'active',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => $basicCentavos,
            // daily_rate and hourly_rate are GENERATED ALWAYS AS STORED — never insert them
            'date_hired' => '2020-01-06',
            'onboarding_status' => 'active',
            'is_active' => true,
        ], self::normalizeOverrides($overrides)))->fresh(); // re-fetch so GENERATED columns (daily_rate, hourly_rate) are populated
    }

    /**
     * Return (or create) a minimal system user for foreign-key requirements.
     */
    public static function makeSystemUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'system-test@ogami.test'],
            [
                'name' => 'System Test',
                'password' => bcrypt('secret'),
            ]
        );
    }

    /**
     * Create a PayrollRun in `processing` status.
     *
     * @param  string  $cutoffStart  e.g., '2025-10-01' (1st cutoff) or '2025-10-16' (2nd)
     * @param  string  $cutoffEnd  e.g., '2025-10-15' (1st cutoff) or '2025-10-31' (2nd)
     */
    public static function makeRun(
        string $cutoffStart = '2025-10-16',
        string $cutoffEnd = '2025-10-31',
        string $runType = 'regular',
        array $overrides = [],
    ): PayrollRun {
        $user = self::makeSystemUser();

        return PayrollRun::create(array_merge([
            'reference_no' => 'RUN-'.strtoupper(substr(uniqid(), -8)),
            'pay_period_label' => date('M Y', strtotime($cutoffStart)),
            'run_type' => $runType,
            'cutoff_start' => $cutoffStart,
            'cutoff_end' => $cutoffEnd,
            'pay_date' => $cutoffEnd,
            'status' => 'processing',
            'created_by' => $user->id,
        ], $overrides));
    }

    /**
     * Create AttendanceLog rows for every work-day between $start and $end (Mon–Fri).
     *
     * @param  int  $overtimeMinutes  OT minutes on EACH working day
     * @param  int  $nightDiffMinutes  Night differential on EACH working day
     * @param  string|null  $holidayType  'regular' | 'special' | null
     */
    public static function makeAttendance(
        Employee $employee,
        string $start,
        string $end,
        bool $present = true,
        int $overtimeMinutes = 0,
        int $nightDiffMinutes = 0,
        ?string $holidayType = null,
    ): void {
        $cursor = new \DateTime($start);
        $endDt = new \DateTime($end);

        while ($cursor <= $endDt) {
            $dow = (int) $cursor->format('N'); // 1=Mon … 7=Sun
            if ($dow <= 5) { // weekday only
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'work_date' => $cursor->format('Y-m-d'),
                    'is_present' => $present,
                    'tardiness_minutes' => 0,
                    'undertime_minutes' => 0,
                    'overtime_minutes' => $overtimeMinutes,
                    'night_diff_minutes' => $nightDiffMinutes,
                    'holiday_type' => $holidayType,
                ]);
            }
            $cursor->modify('+1 day');
        }
    }

    /**
     * Insert a system-settings row (used by the computation pipeline).
     */
    public static function setSetting(string $key, mixed $value): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'data_type' => 'json', 'is_system' => true]
        );
    }
}
