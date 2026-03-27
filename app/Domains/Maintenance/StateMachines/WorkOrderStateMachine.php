<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\StateMachines;

use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Maintenance Work Order state machine.
 *
 * States:
 *   open         → Work order created
 *   in_progress  → Technician started work
 *   completed    → Work finished
 *   cancelled    → Work order cancelled — terminal
 */
final class WorkOrderStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'open' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],   // terminal
        'cancelled' => [],   // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(MaintenanceWorkOrder $wo, string $toState): void
    {
        $fromState = $wo->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('MaintenanceWorkOrder', $fromState, $toState);
        }

        $wo->status = $toState;
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
