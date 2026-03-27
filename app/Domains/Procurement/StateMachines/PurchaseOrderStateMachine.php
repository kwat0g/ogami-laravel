<?php

declare(strict_types=1);

namespace App\Domains\Procurement\StateMachines;

use App\Domains\Procurement\Models\PurchaseOrder;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Purchase Order state machine.
 *
 * States:
 *   draft              → PO created from approved PR
 *   sent               → PO sent to vendor
 *   negotiating        → In price/terms negotiation
 *   acknowledged       → Vendor acknowledged PO
 *   in_transit         → Goods shipped by vendor
 *   delivered          → Goods arrived at receiving area
 *   partially_received → Some items received via Goods Receipt
 *   fully_received     → All items received
 *   closed             → PO completed and closed
 *   cancelled          → PO cancelled
 */
final class PurchaseOrderStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['sent', 'cancelled'],
        'sent' => ['negotiating', 'acknowledged', 'cancelled'],
        'negotiating' => ['sent', 'acknowledged', 'cancelled'],
        'acknowledged' => ['in_transit', 'cancelled'],
        'in_transit' => ['delivered', 'partially_received', 'cancelled'],
        'delivered' => ['partially_received', 'fully_received'],
        'partially_received' => ['fully_received', 'closed'],
        'fully_received' => ['closed'],
        'closed' => [],     // terminal
        'cancelled' => [],  // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(PurchaseOrder $po, string $toState): void
    {
        $fromState = $po->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('PurchaseOrder', $fromState, $toState);
        }

        $po->status = $toState;
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
