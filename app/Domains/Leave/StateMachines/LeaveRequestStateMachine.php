<?php

declare(strict_types=1);

namespace App\Domains\Leave\StateMachines;

use App\Domains\Leave\Models\LeaveRequest;
use App\Shared\Exceptions\DomainException;

/**
 * LeaveRequest state machine.
 *
 * Valid transitions:
 *   draft          -> submitted       (employee files leave)
 *   submitted      -> head_approved   (dept head approves -- legacy: pending -> approved)
 *   submitted      -> rejected        (dept head rejects)
 *   head_approved  -> manager_checked (manager checks)
 *   head_approved  -> rejected        (manager rejects)
 *   manager_checked -> ga_processed   (GA processes)
 *   manager_checked -> rejected       (GA rejects)
 *   ga_processed   -> approved        (final approval)
 *   ga_processed   -> rejected        (final rejection)
 *   approved       -> cancelled       (employee cancels approved leave)
 *   draft          -> cancelled       (employee cancels before submission)
 *
 * Legacy compatibility:
 *   pending        -> approved|rejected|cancelled (single-step approval)
 */
final class LeaveRequestStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft'           => ['submitted', 'cancelled'],
        'submitted'       => ['head_approved', 'rejected'],
        'head_approved'   => ['manager_checked', 'rejected'],
        'manager_checked' => ['ga_processed', 'rejected'],
        'ga_processed'    => ['approved', 'rejected'],
        'approved'        => ['cancelled'],
        'rejected'        => [],
        'cancelled'       => [],

        // Legacy single-step flow
        'pending'         => ['approved', 'rejected', 'cancelled', 'head_approved'],
    ];

    public function canTransition(LeaveRequest $leave, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$leave->status] ?? [], true);
    }

    /**
     * @throws DomainException
     */
    public function transition(LeaveRequest $leave, string $to): void
    {
        if (! $this->canTransition($leave, $to)) {
            throw new DomainException(
                "Cannot transition leave request from '{$leave->status}' to '{$to}'.",
                'LEAVE_INVALID_TRANSITION',
                422,
                ['current' => $leave->status, 'requested' => $to],
            );
        }

        $leave->status = $to;
        $leave->save();
    }

    /** Returns all statuses this leave request can move to from its current state. */
    public function allowedNext(LeaveRequest $leave): array
    {
        return self::TRANSITIONS[$leave->status] ?? [];
    }
}
