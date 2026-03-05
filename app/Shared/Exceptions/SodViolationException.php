<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * HTTP 403 — Separation of Duties violation.
 *
 * Thrown by SodMiddleware when the authenticated user who performed Step A
 * (e.g., initiated a payroll run) attempts to perform Step B (e.g., approve it).
 *
 * The conflict matrix is loaded from system_settings.sod_conflict_matrix;
 * it is never hardcoded.
 */
class SodViolationException extends DomainException
{
    public function __construct(
        public readonly string $processName,
        public readonly string $conflictingAction,
        string $message = '',
    ) {
        $message = $message ?: "Separation of duties violation: you cannot perform '{$conflictingAction}' "
            ."on '{$processName}' because you initiated it.";

        parent::__construct(
            message: $message,
            errorCode: 'SOD_VIOLATION',
            httpStatus: 403,
            context: [
                'process_name' => $processName,
                'conflicting_action' => $conflictingAction,
            ],
        );
    }
}
