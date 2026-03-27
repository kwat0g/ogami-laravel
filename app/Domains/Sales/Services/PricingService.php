<?php

declare(strict_types=1);

namespace App\Domains\Sales\Services;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Sales\Models\PriceList;
use App\Domains\Sales\Models\PriceListItem;
use App\Shared\Contracts\ServiceContract;

/**
 * Pricing Service — resolves the best price for an item given context.
 *
 * Resolution order:
 *   1. Customer-specific price list (active, matching qty tier)
 *   2. Volume discount from default price list
 *   3. Default price list base price
 *   4. Item standard price from ItemMaster
 */
final class PricingService implements ServiceContract
{
    /**
     * Get the resolved price for an item.
     *
     * @return array{unit_price_centavos: int, source: string, price_list_id: int|null}
     */
    public function getPrice(int $itemId, float $quantity = 1.0, ?int $customerId = null): array
    {
        $today = now()->toDateString();

        // 1. Customer-specific price list
        if ($customerId !== null) {
            $customerPrice = PriceListItem::query()
                ->whereHas('priceList', function ($q) use ($customerId, $today): void {
                    $q->where('customer_id', $customerId)
                        ->where('effective_from', '<=', $today)
                        ->where(function ($q2) use ($today): void {
                            $q2->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
                        });
                })
                ->where('item_id', $itemId)
                ->where('min_qty', '<=', $quantity)
                ->where(function ($q) use ($quantity): void {
                    $q->whereNull('max_qty')->orWhere('max_qty', '>=', $quantity);
                })
                ->orderByDesc('min_qty')
                ->first();

            if ($customerPrice !== null) {
                return [
                    'unit_price_centavos' => $customerPrice->unit_price_centavos,
                    'source' => 'customer_price_list',
                    'price_list_id' => $customerPrice->price_list_id,
                ];
            }
        }

        // 2-3. Default price list (volume tier or base)
        $defaultPrice = PriceListItem::query()
            ->whereHas('priceList', function ($q) use ($today): void {
                $q->where('is_default', true)
                    ->where('effective_from', '<=', $today)
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
                    });
            })
            ->where('item_id', $itemId)
            ->where('min_qty', '<=', $quantity)
            ->where(function ($q) use ($quantity): void {
                $q->whereNull('max_qty')->orWhere('max_qty', '>=', $quantity);
            })
            ->orderByDesc('min_qty')
            ->first();

        if ($defaultPrice !== null) {
            return [
                'unit_price_centavos' => $defaultPrice->unit_price_centavos,
                'source' => 'default_price_list',
                'price_list_id' => $defaultPrice->price_list_id,
            ];
        }

        // 4. Fallback to item standard price
        $item = ItemMaster::find($itemId);
        $standardPrice = $item !== null ? (int) (($item->standard_price ?? 0) * 100) : 0;

        return [
            'unit_price_centavos' => $standardPrice,
            'source' => 'item_standard_price',
            'price_list_id' => null,
        ];
    }
}
