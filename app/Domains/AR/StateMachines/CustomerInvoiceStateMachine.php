<?php

declare(strict_types=1);

namespace App\Domains\AR\StateMachines;

use App\Domains\AR\Models\CustomerInvoice;
use App\Shared\Exceptions\DomainException;

/**
 * CustomerInvoice (AR) state machine.
 *
 * Valid transitions:
 *   draft          -> approved        (invoice approved for sending)
 *   approved       -> partially_paid  (partial payment received)
 *   approved       -> paid            (full payment received)
 *   approved       -> written_off     (bad debt write-off)
 *   approved       -> cancelled       (invoice voided)
 *   partially_paid -> paid            (remaining balance collected)
 *   partially_paid -> written_off     (remaining balance written off)
 *   draft          -> cancelled       (draft discarded)
 */
final class CustomerInvoiceStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft'          => ['approved', 'cancelled'],
        'approved'       => ['partially_paid', 'paid', 'written_off', 'cancelled'],
        'partially_paid' => ['paid', 'written_off'],
        'paid'           => [],
        'written_off'    => [],
        'cancelled'      => [],
    ];

    public function canTransition(CustomerInvoice $invoice, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$invoice->status] ?? [], true);
    }

    /**
     * @throws DomainException
     */
    public function transition(CustomerInvoice $invoice, string $to): void
    {
        if (! $this->canTransition($invoice, $to)) {
            throw new DomainException(
                "Cannot transition customer invoice from '{$invoice->status}' to '{$to}'.",
                'AR_INVOICE_INVALID_TRANSITION',
                422,
                ['current' => $invoice->status, 'requested' => $to],
            );
        }

        $invoice->status = $to;
        $invoice->save();
    }

    /** Returns all statuses this invoice can move to from its current state. */
    public function allowedNext(CustomerInvoice $invoice): array
    {
        return self::TRANSITIONS[$invoice->status] ?? [];
    }
}
