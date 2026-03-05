<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveType;
use Illuminate\Database\Seeder;

/**
 * Seeds initial annual leave balances for all active employees.
 *
 * Each non-LWOP leave type is seeded with opening_balance = max_days_per_year
 * for the current year. Uses updateOrCreate so it is safe to re-run —
 * existing records are only updated when opening_balance is still 0.
 */
class LeaveBalanceSeeder extends Seeder
{
    public function run(): void
    {
        $year = (int) date('Y');
        $employees = Employee::whereIn('employment_status', ['active', 'on_leave', 'probationary'])->get();
        $leaveTypes = LeaveType::where('is_active', true)->where('code', '!=', 'LWOP')->get();

        foreach ($employees as $employee) {
            foreach ($leaveTypes as $leaveType) {
                // Only set opening_balance on pre-existing zero records or create new ones.
                $balance = LeaveBalance::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'year' => $year,
                    ],
                    [
                        'opening_balance' => $leaveType->max_days_per_year,
                        'accrued' => 0,
                        'adjusted' => 0,
                        'used' => 0,
                        'monetized' => 0,
                    ],
                );

                // If the record already existed with 0 opening_balance, set it now.
                if ($balance->wasRecentlyCreated === false && $balance->opening_balance == 0 && $balance->used == 0) {
                    $balance->opening_balance = $leaveType->max_days_per_year;
                    $balance->saveQuietly();
                }
            }
        }

        $this->command->info("Seeded leave balances for {$employees->count()} employees × {$leaveTypes->count()} leave types for {$year}.");
    }
}
