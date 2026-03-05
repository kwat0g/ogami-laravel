<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/** HTTP 422 — Leave balance is insufficient for the requested duration (LV-002). */
class InsufficientLeaveBalanceException extends DomainException
{
    public function __construct(
        string $leaveType,
        float $requested,
        float $available,
    ) {
        parent::__construct(
            message: "Insufficient {$leaveType} balance. Requested: {$requested} day(s), available: {$available} day(s).",
            errorCode: 'INSUFFICIENT_LEAVE_BALANCE',
            httpStatus: 422,
            context: [
                'leave_type' => $leaveType,
                'requested_days' => $requested,
                'available_balance' => $available,
            ],
        );
    }
}
