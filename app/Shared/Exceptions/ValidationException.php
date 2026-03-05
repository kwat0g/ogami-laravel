<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * Thrown when domain-level validation (Layer 3) fails.
 * HTTP 422 — Unprocessable Entity.
 *
 * Used for business rule violations that are not caught by the FormRequest
 * (Layer 2), such as minimum wage check or leave balance shortfall.
 *
 * @see BusinessRule — all named rules are tested individually
 */
class ValidationException extends DomainException
{
    public function __construct(
        string $message,
        string $errorCode,
        array $context = [],
        ?string $field = null,
    ) {
        parent::__construct(
            message: $message,
            errorCode: $errorCode,
            httpStatus: 422,
            context: array_filter(array_merge($context, $field ? ['field' => $field] : [])),
        );
    }
}
