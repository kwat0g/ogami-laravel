<?php

declare(strict_types=1);

namespace App\Domains\HR\StateMachines;

use App\Domains\HR\Models\Employee;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Employment lifecycle state machine.
 *
 * States:
 *   draft            → Employee record created but incomplete (EMP-002 onboarding)
 *   active           → Fully onboarded, actively employed
 *   on_leave         → On approved leave of absence (LOA)
 *   suspended        → Suspended pending investigation
 *   resigned         → Voluntary separation — final state
 *   terminated       → Involuntary separation — final state
 *
 * Allowed transitions:
 *   draft       → active (onboarding complete, EMP-003)
 *   active      → on_leave, suspended, resigned, terminated
 *   on_leave    → active (return from LOA), resigned, terminated
 *   suspended   → active (investigation cleared), resigned, terminated
 *
 * resigned and terminated are terminal — no transitions out.
 */
final class EmployeeStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'draft' => ['active'],
        'active' => ['on_leave', 'suspended', 'resigned', 'terminated'],
        'on_leave' => ['active', 'resigned', 'terminated'],
        'suspended' => ['active', 'resigned', 'terminated'],
        'resigned' => [],   // terminal
        'terminated' => [],   // terminal
    ];

    /**
     * Apply a state transition to an Employee model.
     * Throws if the transition is illegal.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(Employee $employee, string $toState): void
    {
        $fromState = $employee->employment_status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('Employee', $fromState, $toState);
        }

        $employee->employment_status = $toState;

        // Side-effects of specific transitions
        match ($toState) {
            'active' => $this->handleActivation($employee),
            'resigned',
            'terminated' => $this->handleSeparation($employee),
            default => null,
        };
    }

    /**
     * Whether a specific transition is valid without applying it.
     */
    public function isAllowed(string $fromState, string $toState): bool
    {
        return in_array($toState, self::TRANSITIONS[$fromState] ?? [], true);
    }

    /**
     * All valid next states from the given current state.
     *
     * @return list<string>
     */
    public function allowedTransitions(string $currentState): array
    {
        return self::TRANSITIONS[$currentState] ?? [];
    }

    // ── Side-effects ──────────────────────────────────────────────────────────

    private function handleActivation(Employee $employee): void
    {
        $employee->is_active = true;

        if ($employee->onboarding_status === 'documents_pending') {
            $employee->onboarding_status = 'active';
        }

        $employee->pendingActivated = true;
    }

    private function handleSeparation(Employee $employee): void
    {
        $employee->is_active = false;

        if ($employee->separation_date === null) {
            $employee->separation_date = now();
        }

        $employee->onboarding_status = 'offboarded';

        $employee->pendingResigned = true;
    }
}
