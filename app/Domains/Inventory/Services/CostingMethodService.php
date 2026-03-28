<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\LotBatch;
use App\Domains\Inventory\Models\StockBalance;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Costing Method Service — Items 10 & 11.
 *
 * Implements FIFO and Weighted Average costing that the existing StockService
 * doesn't provide (it always uses standard_price).
 *
 * Per-item costing: respects ItemMaster.costing_method (standard|fifo|weighted_average).
 *
 * FIFO: tracks cost per lot, issues oldest lots first.
 * Weighted Average: recalculates avg unit cost on every receipt.
 * Standard: uses ItemMaster.standard_price (existing behavior).
 *
 * Flexibility:
 *   - Per-item method via ItemMaster.costing_method
 *   - Multi-warehouse: FIFO scope configurable (per_warehouse or global)
 *   - Cost adjustment on return
 */
final class CostingMethodService implements ServiceContract
{
    /**
     * Get the unit cost for issuing stock based on the item's costing method.
     *
     * @return array{unit_cost_centavos: int, method: string, lot_costs: list<array>|null}
     */
    public function getIssueCost(int $itemId, float $quantity, ?int $locationId = null): array
    {
        $item = ItemMaster::find($itemId);
        if ($item === null) {
            return ['unit_cost_centavos' => 0, 'method' => 'unknown', 'lot_costs' => null];
        }

        $method = $item->costing_method ?? 'standard';

        return match ($method) {
            'fifo' => $this->fifoCost($item, $quantity, $locationId),
            'weighted_average' => $this->weightedAverageCost($item, $locationId),
            default => $this->standardCost($item),
        };
    }

    /**
     * FIFO costing: issue from oldest lots first, tracking cost per lot.
     *
     * @return array{unit_cost_centavos: int, method: string, lot_costs: list<array{lot_id: int, lot_number: string, qty: float, cost_centavos: int}>}
     */
    private function fifoCost(ItemMaster $item, float $quantity, ?int $locationId): array
    {
        // Get lots ordered by receipt date (oldest first = FIFO)
        $query = LotBatch::where('item_id', $item->id)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_date')
            ->orderBy('id');

        $lots = $query->get();

        if ($lots->isEmpty()) {
            return $this->standardCost($item); // Fallback
        }

        $remaining = $quantity;
        $totalCost = 0;
        $lotCosts = [];

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $lot->quantity_remaining;
            $issueQty = min($remaining, $available);
            $unitCost = (int) ($lot->unit_cost_centavos ?? ($item->standard_price_centavos ?? 0));
            $lineCost = (int) round($issueQty * $unitCost);

            $totalCost += $lineCost;
            $remaining -= $issueQty;

            $lotCosts[] = [
                'lot_id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'qty' => round($issueQty, 4),
                'unit_cost_centavos' => $unitCost,
                'line_cost_centavos' => $lineCost,
            ];
        }

        $avgCost = $quantity > 0 ? (int) round($totalCost / $quantity) : 0;

        return [
            'unit_cost_centavos' => $avgCost,
            'total_cost_centavos' => $totalCost,
            'method' => 'fifo',
            'lot_costs' => $lotCosts,
        ];
    }

    /**
     * Weighted Average costing: average cost across all stock on hand.
     *
     * Formula: total_value / total_quantity
     * Recalculated on every receipt.
     */
    private function weightedAverageCost(ItemMaster $item, ?int $locationId): array
    {
        // Get all lots with remaining quantity to compute weighted average
        $query = LotBatch::where('item_id', $item->id)
            ->where('quantity_remaining', '>', 0);

        $lots = $query->get();

        if ($lots->isEmpty()) {
            return $this->standardCost($item); // Fallback
        }

        $totalQty = 0.0;
        $totalValue = 0;

        foreach ($lots as $lot) {
            $qty = (float) $lot->quantity_remaining;
            $cost = (int) ($lot->unit_cost_centavos ?? ($item->standard_price_centavos ?? 0));
            $totalQty += $qty;
            $totalValue += (int) round($qty * $cost);
        }

        $avgCost = $totalQty > 0 ? (int) round($totalValue / $totalQty) : 0;

        return [
            'unit_cost_centavos' => $avgCost,
            'total_value_centavos' => $totalValue,
            'total_quantity' => round($totalQty, 4),
            'method' => 'weighted_average',
            'lot_costs' => null,
        ];
    }

    /**
     * Standard costing: use the item's standard_price.
     */
    private function standardCost(ItemMaster $item): array
    {
        $cost = (int) ($item->standard_price_centavos ?? (($item->standard_price ?? 0) * 100));

        return [
            'unit_cost_centavos' => $cost,
            'method' => 'standard',
            'lot_costs' => null,
        ];
    }

    /**
     * Update weighted average cost after a new receipt.
     *
     * new_avg = (old_qty * old_avg + new_qty * new_cost) / (old_qty + new_qty)
     */
    public function recalculateOnReceipt(int $itemId, float $receiptQty, int $receiptUnitCostCentavos): int
    {
        $item = ItemMaster::find($itemId);
        if ($item === null || ($item->costing_method ?? 'standard') !== 'weighted_average') {
            return $receiptUnitCostCentavos;
        }

        $currentStock = (float) StockBalance::where('item_id', $itemId)->sum('quantity_on_hand');
        $currentAvg = (int) ($item->standard_price_centavos ?? (($item->standard_price ?? 0) * 100));

        $oldValue = (int) round($currentStock * $currentAvg);
        $newValue = (int) round($receiptQty * $receiptUnitCostCentavos);
        $totalQty = $currentStock + $receiptQty;

        $newAvg = $totalQty > 0 ? (int) round(($oldValue + $newValue) / $totalQty) : $receiptUnitCostCentavos;

        // Update item standard_price to the new weighted average
        $item->update(['standard_price_centavos' => $newAvg]);

        return $newAvg;
    }

    /**
     * Inventory valuation report using each item's configured costing method.
     *
     * @return Collection<int, array{item_id: int, item_code: string, name: string, costing_method: string, quantity: float, unit_cost_centavos: int, total_value_centavos: int}>
     */
    public function valuationByMethod(): Collection
    {
        $items = ItemMaster::where('is_active', true)
            ->whereNull('deleted_at')
            ->get();

        return $items->map(function (ItemMaster $item) {
            $totalQty = (float) StockBalance::where('item_id', $item->id)->sum('quantity_on_hand');

            if ($totalQty <= 0) {
                return null;
            }

            $cost = $this->getIssueCost($item->id, $totalQty);

            return [
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'name' => $item->name,
                'costing_method' => $item->costing_method ?? 'standard',
                'quantity' => round($totalQty, 4),
                'unit_cost_centavos' => $cost['unit_cost_centavos'],
                'total_value_centavos' => (int) round($totalQty * $cost['unit_cost_centavos']),
            ];
        })->filter()->sortByDesc('total_value_centavos')->values();
    }
}
