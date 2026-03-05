<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveType;
use Illuminate\Console\Command;

/**
 * Manually accrue leave days for an employee.
 * Useful for new hires who missed monthly accrual runs.
 */
final class AccrueEmployeeLeave extends Command
{
    protected $signature = 'employee:accrue-leave
                            {employee? : Employee ID or code}
                            {--year= : Target year (default: current year)}
                            {--months= : Number of months to accrue (default: since date_hired)}
                            {--all-active : Accrue for ALL active employees}';

    protected $description = 'Manually accrue leave days for an employee';

    public function handle(): int
    {
        $year = (int) $this->option('year') ?: now()->year;

        if ($this->option('all-active')) {
            return $this->accrueForAllActive($year);
        }

        $employeeIdentifier = $this->argument('employee');
        if (! $employeeIdentifier) {
            $this->error('Please provide an employee ID/code or use --all-active');

            return self::FAILURE;
        }

        // Find employee
        $employee = is_numeric($employeeIdentifier)
            ? Employee::find($employeeIdentifier)
            : Employee::where('employee_code', $employeeIdentifier)->first();

        if (! $employee) {
            $this->error("Employee not found: {$employeeIdentifier}");

            return self::FAILURE;
        }

        $months = $this->option('months');
        if (! $months) {
            // Calculate months since hire (max 12 for current year)
            $hireDate = $employee->date_hired;
            if ($hireDate->year < $year) {
                $months = 12; // Full year if hired before this year
            } else {
                $months = max(1, now()->month - $hireDate->month + 1);
            }
        }

        $this->accrueForEmployee($employee, $year, (int) $months);

        return self::SUCCESS;
    }

    private function accrueForAllActive(int $year): int
    {
        $this->info("Accruing leave for all active employees for year {$year}...");

        $processed = 0;

        Employee::where('is_active', true)
            ->where('employment_status', 'active')
            ->chunk(100, function ($employees) use ($year, &$processed) {
                foreach ($employees as $employee) {
                    // Calculate months since hire
                    $hireDate = $employee->date_hired;
                    if ($hireDate->year < $year) {
                        $months = 12;
                    } else {
                        $months = max(1, now()->month - $hireDate->month + 1);
                    }

                    $this->accrueForEmployee($employee, $year, $months, false);
                    $processed++;
                }
            });

        $this->info("Processed {$processed} employees.");

        return self::SUCCESS;
    }

    private function accrueForEmployee(Employee $employee, int $year, int $months, bool $verbose = true): void
    {
        $accrualTypes = LeaveType::where('is_active', true)
            ->whereNotNull('monthly_accrual_days')
            ->where('monthly_accrual_days', '>', 0)
            ->get();

        $totalDays = 0;

        foreach ($accrualTypes as $leaveType) {
            $daysToAccrue = $leaveType->monthly_accrual_days * $months;
            $totalDays += $daysToAccrue;

            $balance = LeaveBalance::firstOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => $year],
                ['opening_balance' => 0, 'accrued' => 0, 'adjusted' => 0, 'used' => 0, 'monetized' => 0]
            );

            $balance->accrued += $daysToAccrue;
            $balance->save();
        }

        if ($verbose) {
            $this->info("✓ {$employee->full_name}: Accrued {$totalDays} days ({$months} months × {$accrualTypes->count()} leave types)");

            // Show current balances
            $this->line('  Current balances:');
            $balances = LeaveBalance::where('employee_id', $employee->id)
                ->where('year', $year)
                ->with('leaveType')
                ->get();
            foreach ($balances as $bal) {
                $this->line("    {$bal->leaveType->code}: {$bal->balance} days");
            }
        }
    }
}
