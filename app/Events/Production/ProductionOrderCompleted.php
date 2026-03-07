<?php

declare(strict_types=1);

namespace App\Events\Production;

use App\Domains\Production\Models\ProductionOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Production Work Order's status transitions to 'completed'.
 * Consumed by Delivery domain to auto-create a draft outbound Delivery Receipt
 * so warehouse staff can confirm outbound shipment of finished goods.
 *
 * PROD-DEL-001: Completing a linked WO initiates the delivery receipt workflow.
 */
final class ProductionOrderCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ProductionOrder $order,
    ) {}
}
