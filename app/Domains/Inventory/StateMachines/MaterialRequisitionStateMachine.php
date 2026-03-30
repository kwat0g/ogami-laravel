<?php

declare(strict_types=1);

namespace App\Domains\Inventory\StateMachines;

use App\Domains\Inventory\Models\MaterialRequisition;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Material Requisition state machine — C1 FIX.
 *
 * Formalizes the 7-stage approval workflow that was previously enforced
 * only via ad-hoc assertStatus() checks in the service layer.
 *
 * States:
 *   draft      → Created, items specified
 *   submitted  → Submitted for approval chain
 *   noted      → Dept Head has noted
 *   checked    → Manager has checked
 *   reviewed   → Officer has reviewed
 *   approved   → VP has approved — ready for warehouse fulfillment
 *   fulfilled  → Warehouse has issued stock for all items
 *   rejected   → Rejected at any approval stage
 *   cancelled  → Cancelled by requester (draft/submitted only)
 */
final class MaterialRequisitionStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft'     => ['submitted', 'cancelled'],
        'submitted' => ['noted', 'rejected', 'cancelled'],
        'noted'     => ['checked', 'rejected'],
        'checked'   => ['reviewed', 'rejected'],
        'reviewed'  => ['approved', 'rejected'],
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
