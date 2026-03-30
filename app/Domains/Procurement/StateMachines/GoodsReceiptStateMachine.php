<?php

declare(strict_types=1);

namespace App\Domains\Procurement\StateMachines;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Goods Receipt state machine — C2 FIX.
 *
 * Formalizes the 8-status workflow that was previously enforced only via
 * ad-hoc if-checks in GoodsReceiptService methods. Prevents out-of-order
 * method calls and ensures GR cannot reach AP invoice creation in a
 * corrupt state.
 *
 * States:
 *   draft              → Created, items entered
 *   submitted          → Submitted for confirmation/QC
 *   pending_qc         → Awaiting incoming quality check
 *   qc_passed          → QC inspection passed
 *   qc_failed          → QC inspection failed (hold for decision)
 *   confirmed          → Confirmed — stock updated, 3-way match triggered
 *   returned           → Returned to supplier
 *   cancelled          → Cancelled before confirmation
 */
final class GoodsReceiptStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft'         => ['submitted', 'confirmed', 'cancelled'],  // confirmed: skip QC if not required
        'submitted'     => ['pending_qc', 'confirmed', 'cancelled'], // confirmed: fast-track non-QC items
        'pending_qc'    => ['qc_passed', 'qc_failed'],
        'qc_passed'     => ['confirmed'],
        'qc_failed'     => ['returned', 'pending_qc', 'cancelled'], // pending_qc: re-inspect after rework
        'confirmed'     => ['returned'],                             // returned: post-confirmation return to supplier
        'returned'      => [],       // terminal
        'cancelled'     => [],       // terminal
    ];

    /**
     * Apply a state transition to a GoodsReceipt model.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(GoodsReceipt $gr, string $toState): void
    {
        $fromState = $gr->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('GoodsReceipt', $fromState, $toState);
        }

        $gr->status = $toState;
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
