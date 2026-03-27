<?php

declare(strict_types=1);

namespace App\Domains\Procurement\StateMachines;

use App\Domains\Procurement\Models\PurchaseRequest;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Purchase Request state machine.
 *
 * States:
 *   draft              → Initial creation
 *   pending_review     → Submitted for review
 *   reviewed           → Reviewed by department head
 *   budget_verified    → Budget checked and verified
 *   returned           → Returned for revision (can be re-submitted)
 *   approved           → Final approval granted
 *   rejected           → Permanently rejected
 *   cancelled          → Cancelled by requester
 *   converted_to_po    → Terminal — converted to a Purchase Order
 */
final class PurchaseRequestStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['pending_review', 'cancelled'],
        'pending_review' => ['reviewed', 'returned', 'rejected', 'cancelled'],
        'reviewed' => ['budget_verified', 'returned', 'rejected'],
        'budget_verified' => ['approved', 'returned', 'rejected'],
        'returned' => ['pending_review', 'cancelled'],
        'approved' => ['converted_to_po', 'cancelled'],
        'rejected' => [],       // terminal
        'cancelled' => [],      // terminal
        'converted_to_po' => [], // terminal
    ];

    /**
     * Apply a state transition to a PurchaseRequest model.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(PurchaseRequest $pr, string $toState): void
    {
        $fromState = $pr->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('PurchaseRequest', $fromState, $toState);
        }

        $pr->status = $toState;
    }

    /**
     * Whether a specific transition is valid without applying it.
     */
    public function isAllowed(string $fromState, string $toState): bool
    {
        return in_array($toState, self::TRANSITIONS[$fromState] ?? [], true);
    }

    /**
     * All valid next states from the given current state.
     *
     * @return list<string>
     */
    public function allowedTransitions(string $currentState): array
    {
        return self::TRANSITIONS[$currentState] ?? [];
    }
}
