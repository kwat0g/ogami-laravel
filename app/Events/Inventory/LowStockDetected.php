<?php

declare(strict_types=1);

namespace App\Events\Inventory;

use App\Domains\Inventory\Models\ItemMaster;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after an issue transaction brings a stock balance at or below the
 * item's configured reorder_point.
 *
 * Listeners:
 *   - NotifyLowStock — sends an in-app notification to all purchasing_officer users.
 */
final class LowStockDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ItemMaster $item,
        public readonly float $currentBalance,
        public readonly int $locationId,
    ) {}
}
