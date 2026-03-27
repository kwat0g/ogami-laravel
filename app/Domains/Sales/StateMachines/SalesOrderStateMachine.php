<?php

declare(strict_types=1);

namespace App\Domains\Sales\StateMachines;

use App\Domains\Sales\Models\SalesOrder;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Sales Order state machine.
 *
 * States:
 *   draft                → Initial creation
 *   confirmed            → Approved, ready for production
 *   in_production        → Production order(s) created
 *   partially_delivered  → Some items delivered
 *   delivered            → All items delivered
 *   invoiced             → AR invoice created
 *   cancelled            → Order cancelled
 */
final class SalesOrderStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['in_production', 'cancelled'],
        'in_production' => ['partially_delivered', 'delivered', 'cancelled'],
        'partially_delivered' => ['delivered'],
        'delivered' => ['invoiced'],
        'invoiced' => [],    // terminal
        'cancelled' => [],   // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(SalesOrder $order, string $toState): void
    {
        $fromState = $order->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('SalesOrder', $fromState, $toState);
        }

        $order->status = $toState;
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
