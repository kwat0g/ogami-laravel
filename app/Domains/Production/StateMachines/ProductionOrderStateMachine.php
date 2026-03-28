<?php

declare(strict_types=1);

namespace App\Domains\Production\StateMachines;

use App\Domains\Production\Models\ProductionOrder;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Production Order state machine — formalizes the manufacturing workflow.
 *
 * States:
 *   draft       → Created, BOM and qty set, not yet released to shop floor
 *   released    → Released to production floor, materials can be requisitioned
 *   in_progress → Active production — output logs being recorded
 *   on_hold     → Temporarily paused (machine down, material shortage, etc.)
 *   completed   → All qty produced and QC passed
 *   closed      → Costs posted to GL, archived
 *   cancelled   → Cancelled before completion
 */
final class ProductionOrderStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft'       => ['released', 'cancelled'],
        'released'    => ['in_progress', 'on_hold', 'cancelled'],
        'in_progress' => ['completed', 'on_hold', 'cancelled'],  // cancelled: emergency stop (material defect, etc.)
        'on_hold'     => ['released', 'in_progress', 'cancelled'],
        'completed'   => ['closed'],
        'closed'      => [],       // terminal
        'cancelled'   => [],       // terminal
    ];

    /**
     * Apply a state transition.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(ProductionOrder $order, string $toState): void
    {
        $fromState = $order->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('ProductionOrder', $fromState, $toState);
        }

        $order->status = $toState;
    }

    public function isAllowed(string $fromState, string $toState): bool
    {
        return in_array($toState, self::TRANSITIONS[$fromState] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public function allowedTransitions(string $currentState): array
    {
        return self::TRANSITIONS[$currentState] ?? [];
    }
}
