<?php

declare(strict_types=1);

namespace App\Domains\Leave\StateMachines;

use App\Domains\Leave\Models\LeaveRequest;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Leave Request state machine — multi-step approval workflow.
 *
 * States:
 *   draft            → Created but not yet submitted
 *   submitted        → Submitted for approval
 *   head_approved    → Department head approved
 *   manager_checked  → Manager checked
 *   ga_processed     → GA/Admin processed
 *   approved         → Final approval — leave granted
 *   rejected         → Rejected at any step — terminal
 *   cancelled        → Cancelled by employee — terminal
 */
final class LeaveRequestStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['head_approved', 'rejected', 'cancelled'],
        'head_approved' => ['manager_checked', 'rejected'],
        'manager_checked' => ['ga_processed', 'rejected'],
        'ga_processed' => ['approved', 'rejected'],
        'approved' => ['cancelled'],
        'rejected' => [],   // terminal
        'cancelled' => [],  // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(LeaveRequest $request, string $toState): void
    {
        $fromState = $request->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('LeaveRequest', $fromState, $toState);
        }

        $request->status = $toState;
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
