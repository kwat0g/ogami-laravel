<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Events\Inventory\ItemPriceChanged;
use App\Shared\Contracts\ServiceContract;

final class GoodsReceiptItemCostSyncService implements ServiceContract
{
    /**
     * Sync item standard prices from the PO agreed unit costs on a confirmed GR.
     *
     * Best-effort by design: failures are logged and swallowed so GR confirmation
     * does not fail due to downstream price maintenance concerns.
     */
    public function syncFromGoodsReceipt(GoodsReceipt $gr): void
    {
        try {
            $gr->loadMissing(['items.poItem', 'purchaseOrder.items']);

            foreach ($gr->items as $grItem) {
                $itemId = $grItem->item_master_id;
                if ($itemId === null) {
                    continue;
                }

                $poItem = $grItem->poItem;
                if ($poItem === null) {
                    continue;
                }

                $agreedCostPesos = (float) ($poItem->agreed_unit_cost ?? 0);
                if ($agreedCostPesos <= 0) {
                    continue;
                }

                $agreedCostCentavos = (int) round($agreedCostPesos * 100);

                $item = ItemMaster::find($itemId);
                if ($item === null) {
                    continue;
                }

                $effectiveQty = $grItem->effectiveAcceptedQuantity();

                if (($item->costing_method ?? 'standard') === 'weighted_average') {
                    try {
                        $costingService = app(\App\Domains\Inventory\Services\CostingMethodService::class);
                        $costingService->recalculateOnReceipt(
                            $itemId,
                            $effectiveQty,
                            $agreedCostCentavos,
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('[GR] Weighted avg recalc failed', [
                            'item_id' => $itemId,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    continue;
                }

                $oldPrice = (int) ($item->standard_price_centavos ?? 0);
                if ($oldPrice === $agreedCostCentavos) {
                    continue;
                }

                $item->update(['standard_price_centavos' => $agreedCostCentavos]);
                event(new ItemPriceChanged($itemId, $oldPrice, $agreedCostCentavos, 'goods_receipt'));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[GR] Item price auto-update failed', [
                'gr_id' => $gr->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}