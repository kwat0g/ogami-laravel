<?php

declare(strict_types=1);

namespace App\Domains\Loan\StateMachines;

use App\Domains\Loan\Models\Loan;
use App\Shared\Exceptions\DomainException;

/**
 * Loan state machine.
 *
 * Valid transitions:
 *   pending             -> head_noted              (dept head notes)
 *   pending             -> cancelled               (employee cancels)
 *   head_noted          -> manager_checked         (manager checks)
 *   head_noted          -> cancelled               (rejected at head level)
 *   manager_checked     -> officer_reviewed        (officer reviews)
 *   manager_checked     -> cancelled               (rejected at manager level)
 *   officer_reviewed    -> supervisor_approved      (supervisor approves)
 *   officer_reviewed    -> cancelled               (rejected at officer level)
 *   supervisor_approved -> approved                 (final approval)
 *   supervisor_approved -> cancelled               (rejected at supervisor level)
 *   approved            -> ready_for_disbursement   (accounting prepares)
 *   ready_for_disbursement -> active               (loan disbursed)
 *   active              -> fully_paid              (all payments complete)
 *   active              -> written_off             (bad debt)
 *   cancelled           -> []                      (terminal)
 */
final class LoanStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending'                => ['head_noted', 'cancelled'],
        'head_noted'             => ['manager_checked', 'cancelled'],
        'manager_checked'        => ['officer_reviewed', 'cancelled'],
        'officer_reviewed'       => ['supervisor_approved', 'cancelled'],
        'supervisor_approved'    => ['approved', 'cancelled'],
        'approved'               => ['ready_for_disbursement'],
        'ready_for_disbursement' => ['active'],
        'active'                 => ['fully_paid', 'written_off'],
        'fully_paid'             => [],
        'written_off'            => [],
        'cancelled'              => [],
    ];

    public function canTransition(Loan $loan, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$loan->status] ?? [], true);
    }

    /**
     * @throws DomainException
     */
    public function transition(Loan $loan, string $to): void
    {
        if (! $this->canTransition($loan, $to)) {
            throw new DomainException(
                "Cannot transition loan from '{$loan->status}' to '{$to}'.",
                'LOAN_INVALID_TRANSITION',
                422,
                ['current' => $loan->status, 'requested' => $to],
            );
        }

        $loan->status = $to;
        $loan->save();
    }

    /** Returns all statuses this loan can move to from its current state. */
    public function allowedNext(Loan $loan): array
    {
        return self::TRANSITIONS[$loan->status] ?? [];
    }
}
