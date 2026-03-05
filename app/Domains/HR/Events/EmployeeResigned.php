<?php

declare(strict_types=1);

namespace App\Domains\HR\Events;

use App\Domains\HR\Models\Employee;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an employee is separated (resigned, terminated, retired).
 * Typically used to trigger final pay computation and SIL monetization.
 */
final class EmployeeResigned
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Employee $employee,
        public readonly string $separationType,  // resigned|terminated|retired
        public readonly string $separationDate,  // YYYY-MM-DD
    ) {}
}
