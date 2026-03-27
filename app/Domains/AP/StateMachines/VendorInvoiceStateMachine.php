<?php

declare(strict_types=1);

namespace App\Domains\AP\StateMachines;

use App\Domains\AP\Models\VendorInvoice;
use App\Shared\Exceptions\DomainException;

/**
 * VendorInvoice (AP) state machine.
 *
 * Valid transitions:
 *   draft            -> pending_approval  (submitted for approval chain)
 *   pending_approval -> head_noted        (dept head notes)
 *   pending_approval -> rejected          (dept head rejects)
 *   head_noted       -> manager_checked   (manager checks)
 *   head_noted       -> rejected          (manager rejects)
 *   manager_checked  -> officer_reviewed  (officer reviews)
 *   manager_checked  -> rejected          (officer rejects)
 *   officer_reviewed -> approved          (final approval)
 *   officer_reviewed -> rejected          (final rejection)
 *   approved         -> partially_paid    (partial payment posted)
 *   approved         -> paid              (full payment posted)
 *   partially_paid   -> paid              (remaining balance paid)
 *   rejected         -> draft             (returned for revision)
 */
final class VendorInvoiceStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft'            => ['pending_approval', 'deleted'],
        'pending_approval' => ['head_noted', 'rejected'],
        'head_noted'       => ['manager_checked', 'rejected'],
        'manager_checked'  => ['officer_reviewed', 'rejected'],
        'officer_reviewed' => ['approved', 'rejected'],
        'approved'         => ['partially_paid', 'paid'],
        'partially_paid'   => ['paid'],
        'paid'             => [],
        'rejected'         => ['draft'],
        'deleted'          => [],
    ];

    public function canTransition(VendorInvoice $invoice, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$invoice->status] ?? [], true);
    }

    /**
     * @throws DomainException
     */
    public function transition(VendorInvoice $invoice, string $to): void
    {
        if (! $this->canTransition($invoice, $to)) {
            throw new DomainException(
                "Cannot transition vendor invoice from '{$invoice->status}' to '{$to}'.",
                'AP_INVOICE_INVALID_TRANSITION',
                422,
                ['current' => $invoice->status, 'requested' => $to],
            );
        }

        $invoice->status = $to;
        $invoice->save();
    }

    /** Returns all statuses this invoice can move to from its current state. */
    public function allowedNext(VendorInvoice $invoice): array
    {
        return self::TRANSITIONS[$invoice->status] ?? [];
    }
}
