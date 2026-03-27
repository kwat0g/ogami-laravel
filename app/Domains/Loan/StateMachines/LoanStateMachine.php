<?php

declare(strict_types=1);

namespace App\Domains\Loan\StateMachines;

use App\Domains\Loan\Models\Loan;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Loan state machine — multi-step approval + lifecycle.
 *
 * States:
 *   pending                → Loan application submitted
 *   head_noted             → Department head noted
 *   manager_checked        → Manager checked
 *   officer_reviewed       → Officer reviewed
 *   supervisor_approved    → Supervisor approved
 *   approved               → Final approval
 *   ready_for_disbursement → Approved and queued for disbursement
 *   active                 → Loan disbursed and active (repayments ongoing)
 *   fully_paid             → All installments paid — terminal
 *   cancelled              → Cancelled before disbursement — terminal
 *   written_off            → Bad debt write-off — terminal
 */
final class LoanStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['head_noted', 'cancelled'],
        'head_noted' => ['manager_checked', 'pending', 'cancelled'],
        'manager_checked' => ['officer_reviewed', 'pending', 'cancelled'],
        'officer_reviewed' => ['supervisor_approved', 'pending', 'cancelled'],
        'supervisor_approved' => ['approved', 'pending', 'cancelled'],
        'approved' => ['ready_for_disbursement', 'cancelled'],
        'ready_for_disbursement' => ['active', 'cancelled'],
        'active' => ['fully_paid', 'written_off'],
        'fully_paid' => [],   // terminal
        'cancelled' => [],    // terminal
        'written_off' => [],  // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(Loan $loan, string $toState): void
    {
        $fromState = $loan->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('Loan', $fromState, $toState);
        }

        $loan->status = $toState;
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
