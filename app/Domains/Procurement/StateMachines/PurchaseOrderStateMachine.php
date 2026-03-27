<?php

declare(strict_types=1);

namespace App\Domains\Procurement\StateMachines;

use App\Domains\Procurement\Models\PurchaseOrder;
use App\Shared\Exceptions\DomainException;

/**
 * PurchaseOrder state machine.
 *
 * Valid transitions:
 *   draft              -> sent                (sent to vendor)
 *   sent               -> negotiating         (vendor negotiates terms)
 *   sent               -> acknowledged        (vendor accepts as-is)
 *   sent               -> cancelled           (buyer cancels)
 *   negotiating        -> sent                (revised PO re-sent)
 *   negotiating        -> cancelled           (negotiations fail)
 *   acknowledged       -> in_transit          (goods shipped)
 *   acknowledged       -> cancelled           (cancelled after ack)
 *   in_transit         -> delivered            (goods arrive at warehouse)
 *   in_transit         -> partially_received   (partial delivery)
 *   delivered          -> partially_received   (partial acceptance after inspection)
 *   delivered          -> fully_received       (all items accepted)
 *   partially_received -> fully_received       (remaining items received)
 *   partially_received -> closed               (accept partial, close out)
 *   fully_received     -> closed               (PO complete)
 *   closed             -> []                   (terminal)
 *   cancelled          -> []                   (terminal)
 */
final class PurchaseOrderStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft'              => ['sent', 'cancelled'],
        'sent'               => ['negotiating', 'acknowledged', 'cancelled'],
        'negotiating'        => ['sent', 'cancelled'],
        'acknowledged'       => ['in_transit', 'cancelled'],
        'in_transit'         => ['delivered', 'partially_received'],
        'delivered'          => ['partially_received', 'fully_received'],
        'partially_received' => ['fully_received', 'closed'],
        'fully_received'     => ['closed'],
        'closed'             => [],
        'cancelled'          => [],
    ];

    public function canTransition(PurchaseOrder $po, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$po->status] ?? [], true);
    }

    /**
     * @throws DomainException
     */
    public function transition(PurchaseOrder $po, string $to): void
    {
        if (! $this->canTransition($po, $to)) {
            throw new DomainException(
                "Cannot transition purchase order from '{$po->status}' to '{$to}'.",
                'PO_INVALID_TRANSITION',
                422,
                ['current' => $po->status, 'requested' => $to],
            );
        }

        $po->status = $to;
        $po->save();
    }

    /** Returns all statuses this PO can move to from its current state. */
    public function allowedNext(PurchaseOrder $po): array
    {
        return self::TRANSITIONS[$po->status] ?? [];
    }
}
