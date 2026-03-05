<?php

declare(strict_types=1);

namespace App\Domains\HR\Events;

use App\Domains\HR\Models\Employee;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an employee transitions to 'active' onboarding status.
 * Listener: CreateLeaveBalances
 */
final class EmployeeActivated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Employee $employee,
    ) {}
}
