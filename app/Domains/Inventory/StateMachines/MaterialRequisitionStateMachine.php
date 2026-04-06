<?php

declare(strict_types=1);

namespace App\Domains\Inventory\StateMachines;

use App\Domains\Inventory\Models\MaterialRequisition;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Material Requisition state machine — simplified 2-step approval.
 *
 * Shortened workflow: Requester submits → Warehouse Manager approves → Warehouse fulfills.
 *
 * States:
 *   draft      → Created, items specified
 *   submitted  → Submitted for Warehouse Manager approval
 *   approved   → Warehouse Manager approved — ready for fulfillment
 *   fulfilled  → Warehouse has issued stock for all items
 *   rejected   → Rejected by Warehouse Manager
 *   cancelled  → Cancelled by requester (draft/submitted only)
 */
final class MaterialRequisitionStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft'     => ['submitted', 'cancelled'],
        'submitted' => ['approved', 'rejected', 'cancelled'],
        'approved'  => ['fulfilled', 'cancelled'],
        'fulfilled' => [],       // terminal
        'rejected'  => ['draft'], // can be returned to draft for revision
        'cancelled' => [],       // terminal
    ];

    /**
     * Apply a state transition to a MaterialRequisition model.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(MaterialRequisition $mrq, string $toState): void
    {
        $fromState = $mrq->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('MaterialRequisition', $fromState, $toState);
        }

        $mrq->status = $toState;
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
