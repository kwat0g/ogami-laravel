<?php

declare(strict_types=1);

namespace App\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an item's standard_price_centavos is updated
 * (e.g., from GR confirmation updating prices from PO costs).
 *
 * Listeners should trigger BOM cost rollup for any BOM
 * containing this item as a component.
 */
final class ItemPriceChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $itemId,
        public readonly int $oldPriceCentavos,
        public readonly int $newPriceCentavos,
        public readonly string $source,
    ) {}
}
