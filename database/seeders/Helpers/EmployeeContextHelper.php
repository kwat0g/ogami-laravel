<?php

declare(strict_types=1);

namespace Database\Seeders\Helpers;

use Illuminate\Support\Facades\DB;

/**
 * Helper class to ensure fresh databases are populated with realistic 
 * contextual data for employees, such as default leave balances and shifts.
 */
class EmployeeContextHelper
{
    /**
     * Allocate baseline leave balances for the given employee for the given year.
     * Uses max_days_per_year from leave_types.
     */
    public static function allocateLeaveBalances(int $employeeId, ?int $year = null): void
    {
        $year ??= (int) now()->year;

        $leaveTypes = DB::table('leave_types')
            ->where('is_active', true)
            ->get();

        $balances = [];
        $now = now();

        foreach ($leaveTypes as $type) {
            // Allocate the max allowed per year, except for 'Others' (OTH)
            $accrued = $type->code === 'OTH' ? 0.00 : (float) ($type->max_days_per_year ?? 0);

            $balances[] = [
                'employee_id' => $employeeId,
                'leave_type_id' => $type->id,
                'year' => $year,
                'opening_balance' => 0.00,
                'accrued' => $accrued,
                'used' => 0.00,
                'adjusted' => 0.00,
                'monetized' => 0.00,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($balances)) {
            DB::table('leave_balances')->insertOrIgnore($balances);
        }
    }

    /**
     * Assigns the default regular day shift (8AM-5PM) if none exists.
     */
    public static function assignDefaultShift(int $employeeId): void
    {
        // Skip if already has an assignment validation
        $exists = DB::table('employee_shift_assignments')
            ->where('employee_id', $employeeId)
            ->exists();

        if ($exists) {
            return;
        }

        $regularShift = DB::table('shift_schedules')
            ->where('start_time', '08:00:00')
            ->where('is_active', true)
            ->first();

        if (! $regularShift) {
            return;
        }

        $assignedBy = DB::table('users')->first()?->id ?? 1;
        $employee = DB::table('employees')->where('id', $employeeId)->first();

        DB::table('employee_shift_assignments')->insertOrIgnore([
            'employee_id' => $employeeId,
            'shift_schedule_id' => $regularShift->id,
            'effective_from' => $employee?->date_hired ?? now()->toDateString(),
            'effective_to' => null,
            'notes' => 'Initial shift assignment (seeded)',
            'assigned_by' => $assignedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
