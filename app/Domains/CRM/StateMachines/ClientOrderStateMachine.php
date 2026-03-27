<?php

declare(strict_types=1);

namespace App\Domains\CRM\StateMachines;

use App\Domains\CRM\Models\ClientOrder;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Client Order state machine.
 *
 * States:
 *   pending           → Order submitted by client
 *   negotiating       → Sales made proposal, waiting for client
 *   client_responded  → Client made counter-proposal
 *   vp_pending        → Awaiting VP approval (high-value orders)
 *   approved          → Order approved — terminal (triggers production)
 *   rejected          → Order rejected — terminal
 *   cancelled         → Order cancelled — terminal
 */
final class ClientOrderStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['negotiating', 'vp_pending', 'approved', 'rejected', 'cancelled'],
        'negotiating' => ['client_responded', 'approved', 'rejected', 'cancelled'],
        'client_responded' => ['negotiating', 'vp_pending', 'approved', 'rejected', 'cancelled'],
        'vp_pending' => ['approved', 'rejected', 'cancelled'],
        'approved' => [],   // terminal
        'rejected' => [],   // terminal
        'cancelled' => [],  // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(ClientOrder $order, string $toState): void
    {
        $fromState = $order->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('ClientOrder', $fromState, $toState);
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
