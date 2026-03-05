<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/** HTTP 409 — Attempt to create or modify records in a locked fiscal period (ATT-006, JE-004). */
class LockedPeriodException extends DomainException
{
    public function __construct(string $periodLabel, string $reason = '')
    {
        $message = "The period '{$periodLabel}' is locked and cannot be modified.";
        if ($reason) {
            $message .= " Reason: {$reason}";
        }

        parent::__construct(
            message: $message,
            errorCode: 'LOCKED_PERIOD',
            httpStatus: 409,
            context: [
                'period' => $periodLabel,
                'reason' => $reason,
            ],
        );
    }
}
