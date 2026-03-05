<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/** HTTP 403 — role or department access denied. */
class AuthorizationException extends DomainException
{
    public function __construct(
        string $message = 'You are not authorized to perform this action.',
        string $errorCode = 'AUTHORIZATION_FAILED',
        array $context = [],
    ) {
        parent::__construct($message, $errorCode, 403, $context);
    }
}
