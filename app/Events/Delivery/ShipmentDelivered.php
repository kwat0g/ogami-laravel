<?php

declare(strict_types=1);

namespace App\Events\Delivery;

use App\Domains\Delivery\Models\Shipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Shipment's status transitions to 'delivered'.
 * Consumed by the AR domain to auto-create a draft Customer Invoice
 * so finance staff can review and approve billing for the delivered order.
 */
final class ShipmentDelivered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Shipment $shipment,
    ) {}
}
