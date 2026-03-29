<?php

declare(strict_types=1);

namespace App\Listeners\Inventory;

use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Services\BomService;
use App\Events\Inventory\ItemPriceChanged;
use Illuminate\Support\Facades\Log;

/**
 * When an item's standard price changes (e.g., from GR price update),
 * automatically re-rolls BOM costs for any active BOM containing
 * that item as a component.
 */
class RollupBomCostsOnPriceChange
{
    public function handle(ItemPriceChanged $event): void
    {
        $affectedBomIds = BomComponent::where('component_item_id', $event->itemId)
            ->pluck('bill_of_materials_id')
            ->unique();

        if ($affectedBomIds->isEmpty()) {
            return;
        }

        $boms = BillOfMaterials::whereIn('id', $affectedBomIds)
            ->where('is_active', true)
            ->get();

        if ($boms->isEmpty()) {
            return;
        }

        try {
            $bomService = app(BomService::class);

            foreach ($boms as $bom) {
                $bomService->rollupCost($bom);
                Log::info('[BOM Rollup] Auto-recalculated BOM cost after item price change', [
                    'bom_id' => $bom->id,
                    'item_id' => $event->itemId,
                    'old_price' => $event->oldPriceCentavos,
                    'new_price' => $event->newPriceCentavos,
                    'new_bom_cost' => $bom->fresh()->standard_cost_centavos,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[BOM Rollup] Failed to auto-rollup BOM costs', [
                'item_id' => $event->itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
