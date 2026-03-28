<?php

declare(strict_types=1);

namespace App\Domains\Inventory\StateMachines;

use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Physical Count state machine.
 *
 * States:
 *   draft             -> Initial creation, items pre-populated from stock
 *   in_progress       -> Counting underway, quantities being recorded
 *   pending_approval  -> All items counted, awaiting supervisor approval
 *   approved          -> Approved, stock adjustments posted
 *   cancelled         -> Count cancelled before approval
 */
final class PhysicalCountStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['in_progress', 'cancelled'],
        'in_progress' => ['pending_approval', 'cancelled'],
        'pending_approval' => ['approved', 'in_progress', 'cancelled'],
        'approved' => [],     // terminal
        'cancelled' => [],    // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(object $physicalCount, string $toState): void
    {
        $fromState = $physicalCount->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('PhysicalCount', $fromState, $toState);
        }

        $physicalCount->status = $toState;
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
