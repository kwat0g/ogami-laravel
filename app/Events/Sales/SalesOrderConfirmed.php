<?php

declare(strict_types=1);

namespace App\Events\Sales;

use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Sales Order transitions to 'confirmed' status.
 *
 * Consumed by the Delivery domain to auto-create a draft outbound
 * Delivery Receipt for stock-available items, closing the
 * SO → DR chain gap (CHAIN-SO-DR-001).
 */
final class SalesOrderConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SalesOrder $order,
    ) {}
}
