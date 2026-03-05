<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * Base exception for all domain-layer errors.
 *
 * Every exception thrown by a Domain Service or Business Rule must extend
 * this class. Never throw a generic \Exception or \RuntimeException directly
 * from the domain — doing so bypasses the structured error response and the
 * machine-readable error code required by the frontend.
 *
 * The Handler maps every DomainException subclass to the correct HTTP status
 * and the standard JSON envelope:
 *   { "success": false, "error": { "code": "...", "message": "...", ... } }
 */
class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus,
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
