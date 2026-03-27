<?php

declare(strict_types=1);

namespace App\Domains\Budget\StateMachines;

use App\Domains\Budget\Models\AnnualBudget;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Budget approval state machine.
 *
 * States:
 *   draft      → Initial state, budget creator can edit
 *   submitted  → Sent for review by department head
 *   reviewed   → Reviewed by finance, pending final approval
 *   approved   → Approved — ready for enforcement
 *   rejected   → Rejected — back to draft
 *   locked     → Locked for the fiscal year — no changes
 */
final class BudgetStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['reviewed', 'rejected'],
        'reviewed' => ['approved', 'rejected'],
        'approved' => ['locked'],
        'rejected' => ['draft'],
        'locked' => [],  // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(AnnualBudget $budget, string $toState): void
    {
        $fromState = $budget->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('AnnualBudget', $fromState, $toState);
        }

        $budget->status = $toState;
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
