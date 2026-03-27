<?php

declare(strict_types=1);

namespace App\Domains\AR\StateMachines;

use App\Domains\AR\Models\CustomerInvoice;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Customer Invoice (AR) state machine.
 *
 * States:
 *   draft           → Invoice created / auto-drafted from DR
 *   approved        → Approved and sent to customer
 *   partially_paid  → Some payments received
 *   paid            → Fully paid — terminal
 *   written_off     → Bad debt write-off — terminal
 *   cancelled       → Voided — terminal
 */
final class CustomerInvoiceStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['approved', 'cancelled'],
        'approved' => ['partially_paid', 'paid', 'written_off', 'cancelled'],
        'partially_paid' => ['paid', 'written_off'],
        'paid' => [],        // terminal
        'written_off' => [], // terminal
        'cancelled' => [],   // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(CustomerInvoice $invoice, string $toState): void
    {
        $fromState = $invoice->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('CustomerInvoice', $fromState, $toState);
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
