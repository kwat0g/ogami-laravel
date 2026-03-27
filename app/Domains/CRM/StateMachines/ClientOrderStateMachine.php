<?php

declare(strict_types=1);

namespace App\Domains\CRM\StateMachines;

use App\Domains\CRM\Models\ClientOrder;
use App\Shared\Exceptions\DomainException;

/**
 * ClientOrder state machine.
 *
 * Valid transitions:
 *   pending           -> negotiating        (sales makes proposal)
 *   pending           -> approved           (direct approval, low value)
 *   pending           -> vp_pending         (high-value, needs VP)
 *   pending           -> rejected           (sales rejects order)
 *   pending           -> cancelled          (client cancels)
 *   negotiating       -> client_responded   (client counter-proposes)
 *   negotiating       -> approved           (client accepts proposal)
 *   negotiating       -> rejected           (negotiations fail)
 *   negotiating       -> cancelled          (either party cancels)
 *   client_responded  -> negotiating        (sales reviews counter)
 *   client_responded  -> approved           (sales accepts counter)
 *   client_responded  -> rejected           (sales rejects counter)
 *   vp_pending        -> approved           (VP approves)
 *   vp_pending        -> rejected           (VP rejects)
 *   approved          -> cancelled          (order cancelled after approval)
 */
final class ClientOrderStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending'           => ['negotiating', 'approved', 'vp_pending', 'rejected', 'cancelled'],
        'negotiating'       => ['client_responded', 'approved', 'rejected', 'cancelled'],
        'client_responded'  => ['negotiating', 'approved', 'rejected'],
        'vp_pending'        => ['approved', 'rejected'],
        'approved'          => ['cancelled'],
        'rejected'          => [],
        'cancelled'         => [],
    ];

    public function canTransition(ClientOrder $order, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$order->status] ?? [], true);
    }

    /**
     * @throws DomainException
     */
    public function transition(ClientOrder $order, string $to): void
    {
        if (! $this->canTransition($order, $to)) {
            throw new DomainException(
                "Cannot transition client order from '{$order->status}' to '{$to}'.",
                'CLIENT_ORDER_INVALID_TRANSITION',
                422,
                ['current' => $order->status, 'requested' => $to],
            );
        }

        $order->status = $to;
        $order->save();
    }

    /** Returns all statuses this order can move to from its current state. */
    public function allowedNext(ClientOrder $order): array
    {
        return self::TRANSITIONS[$order->status] ?? [];
    }
}
