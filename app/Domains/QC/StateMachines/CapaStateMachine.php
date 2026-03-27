<?php

declare(strict_types=1);

namespace App\Domains\QC\StateMachines;

use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * CAPA (Corrective and Preventive Action) state machine.
 *
 * States:
 *   draft        -> Initial creation
 *   assigned     -> Assigned to responsible person
 *   in_progress  -> Work underway
 *   verification -> Completed, awaiting verification
 *   closed       -> Verified and closed
 *   rejected     -> Rejected during verification
 */
final class CapaStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['assigned'],
        'assigned' => ['in_progress', 'rejected'],
        'in_progress' => ['verification'],
        'verification' => ['closed', 'rejected', 'in_progress'],
        'closed' => [],      // terminal
        'rejected' => ['draft'],  // can restart
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(object $capa, string $toState): void
    {
        $fromState = $capa->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('CapaAction', $fromState, $toState);
        }

        $capa->status = $toState;
    }

    public function isAllowed(string $fromState, string $toState): bool
    {
        return in_array($toState, self::TRANSITIONS[$fromState] ?? [], true);
    }

    /** @return list<string> */
    public function allowedTransitions(string $currentState): array
    {
        return self::TRANSITIONS[$currentState] ?? [];
    }
}
