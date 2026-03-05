<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveType;
use Illuminate\Console\Command;

/**
 * Set opening balance for a specific leave type for an employee.
 * Useful for granting lump-sum leave types (ML, PL, SPL, VAWCL) or adjusting balances.
 */
final class SetEmployeeLeaveOpeningBalance extends Command
{
    protected $signature = 'employee:set-opening-balance
                            {employee : Employee ID or code}
                            {leave-type : Leave type code (VL, SL, ML, PL, SPL, VAWCL, SIL, LWOP)}
                            {days : Number of days to set as opening balance}
                            {--year= : Target year (default: current year)}
                            {--all-active : Set for ALL active employees}';

    protected $description = 'Set opening leave balance for an employee';

    public function handle(): int
    {
        $year = (int) $this->option('year') ?: now()->year;
        $leaveTypeCode = strtoupper($this->argument('leave-type'));
        $days = (float) $this->argument('days');

        // Find leave type
        $leaveType = LeaveType::where('code', $leaveTypeCode)->where('is_active', true)->first();
        if (! $leaveType) {
            $this->error("Leave type not found: {$leaveTypeCode}");

            return self::FAILURE;
        }

        if ($this->option('all-active')) {
            return $this->setForAllActive($leaveType, $year, $days);
        }

        $employeeIdentifier = $this->argument('employee');

        // Find employee
        $employee = is_numeric($employeeIdentifier)
            ? Employee::find($employeeIdentifier)
            : Employee::where('employee_code', $employeeIdentifier)->first();

        if (! $employee) {
            $this->error("Employee not found: {$employeeIdentifier}");

            return self::FAILURE;
        }

        $this->setOpeningBalance($employee, $leaveType, $year, $days);

        $this->info("✓ Set {$leaveType->name} opening balance to {$days} days for {$employee->full_name}");

        return self::SUCCESS;
    }

    private function setForAllActive(LeaveType $leaveType, int $year, float $days): int
    {
        $this->info("Setting {$leaveType->name} opening balance to {$days} days for all active employees...");

        $processed = 0;

        Employee::where('is_active', true)->chunk(100, function ($employees) use ($leaveType, $year, $days, &$processed) {
            foreach ($employees as $employee) {
                $this->setOpeningBalance($employee, $leaveType, $year, $days);
                $processed++;
            }
        });

        $this->info("Processed {$processed} employees.");

        return self::SUCCESS;
    }

    private function setOpeningBalance(Employee $employee, LeaveType $leaveType, int $year, float $days): void
    {
        $balance = LeaveBalance::firstOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => $year],
            ['opening_balance' => 0, 'accrued' => 0, 'adjusted' => 0, 'used' => 0, 'monetized' => 0]
        );

        $balance->opening_balance = $days;
        $balance->save();
    }
}
