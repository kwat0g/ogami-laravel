<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * HTTP 409 — Attempted an illegal state machine transition.
 *
 * Every lifecycle entity (Employee, PayrollRun, LeaveRequest, etc.) has an
 * explicit StateMachine. Transitions not defined in the machine's allowed map
 * throw this exception.
 */
class InvalidStateTransitionException extends DomainException
{
    public function __construct(
        public readonly string $entityType,
        public readonly string $fromState,
        public readonly string $toState,
    ) {
        parent::__construct(
            message: "Cannot transition {$entityType} from '{$fromState}' to '{$toState}'.",
            errorCode: 'INVALID_STATE_TRANSITION',
            httpStatus: 409,
            context: [
                'entity' => $entityType,
                'from_state' => $fromState,
                'to_state' => $toState,
            ],
        );
    }
}
