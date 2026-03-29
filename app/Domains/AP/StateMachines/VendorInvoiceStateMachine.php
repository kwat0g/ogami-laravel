<?php

declare(strict_types=1);

namespace App\Domains\AP\StateMachines;

use App\Domains\AP\Models\VendorInvoice;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Vendor Invoice (AP) state machine — multi-step approval workflow.
 *
 * States:
 *   draft              → Invoice created / auto-drafted from GR
 *   pending_approval   → Submitted for approval chain
 *   head_noted         → Department head noted
 *   manager_checked    → Manager checked
 *   officer_reviewed   → Officer reviewed
 *   approved           → Final approval — ready for payment
 *   partially_paid     → Some payments applied
 *   paid               → Fully paid — terminal
 *   deleted            → Soft-deleted / voided — terminal
 */
final class VendorInvoiceStateMachine
{
    /**
     * REC-14: Approval steps now return to the PREVIOUS step instead of draft.
     * This avoids forcing re-approval through the entire chain for minor corrections.
     *
     * Return paths:
     *   head_noted       → pending_approval (return to submitter)
     *   manager_checked  → head_noted (return to head for re-review)
     *   officer_reviewed → manager_checked (return to manager)
     *
     * 'draft' is still reachable from pending_approval (the first approval step).
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'draft' => ['pending_approval', 'deleted'],
        'pending_approval' => ['head_noted', 'draft', 'deleted'],
        'head_noted' => ['manager_checked', 'pending_approval', 'deleted'],
        'manager_checked' => ['officer_reviewed', 'head_noted', 'deleted'],
        'officer_reviewed' => ['approved', 'manager_checked', 'deleted'],
        'approved' => ['partially_paid', 'paid'],
        'partially_paid' => ['paid'],
        'paid' => [],    // terminal
        'deleted' => [],  // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(VendorInvoice $invoice, string $toState): void
    {
        $fromState = $invoice->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('VendorInvoice', $fromState, $toState);
        }

        $invoice->status = $toState;
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
