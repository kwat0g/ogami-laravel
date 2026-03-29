<?php

declare(strict_types=1);

namespace App\Domains\Attendance\StateMachines;

use App\Domains\Attendance\Models\OvertimeRequest;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Overtime Request state machine — multi-step approval workflow.
 *
 * States:
 *   pending              → OT request submitted
 *   supervisor_approved  → Direct supervisor approved
 *   manager_checked      → Plant manager checked
 *   officer_reviewed     → HR/Admin officer reviewed
 *   approved             → VP/Executive final approval — terminal (approved)
 *   rejected             → Rejected at any step — terminal
 *   cancelled            → Cancelled by employee — terminal
 */
final class OvertimeRequestStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['supervisor_approved', 'rejected', 'cancelled'],
        'supervisor_approved' => ['manager_checked', 'rejected', 'cancelled'],
        'manager_checked' => ['officer_reviewed', 'rejected', 'cancelled'],
        'officer_reviewed' => ['approved', 'rejected', 'cancelled'],
        'approved' => [],     // terminal
        'rejected' => [],     // terminal
        'cancelled' => [],    // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(OvertimeRequest $request, string $toState): void
    {
        $fromState = $request->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('OvertimeRequest', $fromState, $toState);
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
