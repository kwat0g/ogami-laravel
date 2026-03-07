<?php

declare(strict_types=1);

namespace App\Domains\HR\Listeners;

use App\Domains\HR\Events\EmployeeActivated;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveType;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * When an employee becomes active, provision a zeroed LeaveBalance row
 * for every active leave type for the current year.
 *
 * The LeaveAccrualService will populate accrued days on the next accrual run.
 * Queued to avoid blocking the activation request.
 */
final class CreateLeaveBalances implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(EmployeeActivated $event): void
    {
        $year = now()->year;
        $employee = $event->employee;

        // Only auto-create for standard leave types.
        // OTH (Others) is discretionary — no fixed entitlement; skip balance row.
        LeaveType::where('is_active', true)
            ->whereNotIn('code', ['OTH'])
            ->each(function (LeaveType $type) use ($employee, $year) {
                LeaveBalance::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_type_id' => $type->id,
                        'year' => $year,
                    ],
                    [
                        'opening_balance' => 0.0,
                        'accrued' => 0.0,
                        'adjusted' => 0.0,
                        'used' => 0.0,
                        'monetized' => 0.0,
                    ],
                );
            });
    }
}
