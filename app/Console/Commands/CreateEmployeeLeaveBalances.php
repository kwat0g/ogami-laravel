<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveType;
use Illuminate\Console\Command;

/**
 * Manually create leave balances for an employee.
 * Useful for fixing missing balances or onboarding employees
 * who were activated before the auto-creation was implemented.
 */
final class CreateEmployeeLeaveBalances extends Command
{
    protected $signature = 'employee:create-leave-balances
                            {employee? : Employee ID or code (e.g., EMP-2026-000001)}
                            {--year= : Target year (default: current year)}
                            {--opening-balance= : Set initial opening balance (default: 0)}
                            {--all-active : Create balances for ALL active employees missing them}';

    protected $description = 'Create leave balance records for an employee';

    public function handle(): int
    {
        $year = (int) $this->option('year') ?: now()->year;
        $openingBalance = (float) $this->option('opening-balance') ?: 0.0;

        if ($this->option('all-active')) {
            return $this->createForAllActiveEmployees($year, $openingBalance);
        }

        $employeeIdentifier = $this->argument('employee');
        if (! $employeeIdentifier) {
            $this->error('Please provide an employee ID/code or use --all-active');

            return self::FAILURE;
        }

        // Find employee by ID or code
        $employee = is_numeric($employeeIdentifier)
            ? Employee::find($employeeIdentifier)
            : Employee::where('employee_code', $employeeIdentifier)->first();

        if (! $employee) {
            $this->error("Employee not found: {$employeeIdentifier}");

            return self::FAILURE;
        }

        $this->createBalancesForEmployee($employee, $year, $openingBalance);

        $this->info("✓ Created leave balances for {$employee->full_name} ({$employee->employee_code})");

        return self::SUCCESS;
    }

    private function createForAllActiveEmployees(int $year, float $openingBalance): int
    {
        $this->info("Creating leave balances for all active employees missing them for year {$year}...");

        $createdCount = 0;
        $skipCount = 0;

        Employee::where('is_active', true)->chunk(100, function ($employees) use ($year, $openingBalance, &$createdCount, &$skipCount) {
            foreach ($employees as $employee) {
                $hasAnyBalance = LeaveBalance::where('employee_id', $employee->id)
                    ->where('year', $year)
                    ->exists();

                if ($hasAnyBalance) {
                    $skipCount++;

                    continue;
                }

                $this->createBalancesForEmployee($employee, $year, $openingBalance);
                $createdCount++;
                $this->line("  ✓ {$employee->full_name}");
            }
        });

        $this->newLine();
        $this->info("Created balances for {$createdCount} employees, skipped {$skipCount} (already had balances).");

        return self::SUCCESS;
    }

    private function createBalancesForEmployee(Employee $employee, int $year, float $openingBalance): void
    {
        LeaveType::where('is_active', true)->each(function (LeaveType $type) use ($employee, $year, $openingBalance): void {
            LeaveBalance::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'year' => $year,
                ],
                [
                    'opening_balance' => $openingBalance,
                    'accrued' => 0.0,
                    'adjusted' => 0.0,
                    'used' => 0.0,
                    'monetized' => 0.0,
                ],
            );
        });
    }
}
