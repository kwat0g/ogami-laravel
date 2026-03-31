<?php

declare(strict_types=1);

namespace App\Domains\Delivery\StateMachines;

use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Delivery Receipt state machine.
 *
 * M6 FIX: Added 'partially_delivered' state for partial shipments.
 * Added 'dispatched' state to track when goods leave the warehouse.
 *
 * States:
 *   draft               → DR created but not confirmed
 *   confirmed           → DR confirmed and ready for dispatch
 *   dispatched          → DR left warehouse (shipment prepared, goods picked)
 *   in_transit          → Goods physically en route to customer
 *   partially_delivered  → Some items delivered, others pending
 *   delivered            → All goods delivered to customer (POD recorded)
 *   cancelled            → DR cancelled — terminal
 */
final class DeliveryReceiptStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['dispatched', 'cancelled'],
        'dispatched' => ['in_transit', 'partially_delivered', 'delivered', 'cancelled'],
        'in_transit' => ['partially_delivered', 'delivered', 'cancelled'],
        'partially_delivered' => ['delivered', 'cancelled'],
        'delivered' => [],   // terminal
        'cancelled' => [],   // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(DeliveryReceipt $dr, string $toState): void
    {
        $fromState = $dr->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('DeliveryReceipt', $fromState, $toState);
        }

        $dr->status = $toState;
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
