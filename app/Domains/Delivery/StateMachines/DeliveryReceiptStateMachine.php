<?php

declare(strict_types=1);

namespace App\Domains\Delivery\StateMachines;

use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Delivery Receipt state machine.
 *
 * States:
 *   draft      → DR created but not confirmed
 *   confirmed  → DR confirmed and ready for delivery
 *   delivered  → Goods delivered to customer
 *   cancelled  → DR cancelled — terminal
 */
final class DeliveryReceiptStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['delivered', 'cancelled'],
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
