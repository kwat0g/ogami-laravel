<?php

declare(strict_types=1);

namespace App\Domains\Procurement\StateMachines;

use App\Domains\Procurement\Models\PurchaseRequest;
use App\Shared\Exceptions\DomainException;

/**
 * PurchaseRequest state machine.
 *
 * Valid transitions:
 *   draft           -> pending_review    (submitted by requestor)
 *   pending_review  -> reviewed          (dept head reviews)
 *   pending_review  -> returned          (dept head returns for revision)
 *   pending_review  -> rejected          (dept head rejects outright)
 *   reviewed        -> budget_verified   (accounting verifies budget)
 *   reviewed        -> returned          (accounting returns)
 *   budget_verified -> approved          (VP/management approves)
 *   budget_verified -> rejected          (VP rejects)
 *   approved        -> converted_to_po   (PO created from PR)
 *   returned        -> draft             (requestor revises)
 *   rejected        -> draft             (requestor may resubmit)
 *   draft           -> cancelled         (requestor cancels)
 */
final class PurchaseRequestStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft'           => ['pending_review', 'cancelled'],
        'pending_review'  => ['reviewed', 'returned', 'rejected'],
        'reviewed'        => ['budget_verified', 'returned'],
        'budget_verified' => ['approved', 'rejected'],
        'approved'        => ['converted_to_po'],
        'returned'        => ['draft'],
        'rejected'        => ['draft'],
        'converted_to_po' => [],
        'cancelled'       => [],
    ];

    public function canTransition(PurchaseRequest $pr, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$pr->status] ?? [], true);
    }

    /**
     * @throws DomainException
     */
    public function transition(PurchaseRequest $pr, string $to): void
    {
        if (! $this->canTransition($pr, $to)) {
            throw new DomainException(
                "Cannot transition purchase request from '{$pr->status}' to '{$to}'.",
                'PR_INVALID_TRANSITION',
                422,
                ['current' => $pr->status, 'requested' => $to],
            );
        }

        $pr->status = $to;
        $pr->save();
    }

    /** Returns all statuses this PR can move to from its current state. */
    public function allowedNext(PurchaseRequest $pr): array
    {
        return self::TRANSITIONS[$pr->status] ?? [];
    }
}
