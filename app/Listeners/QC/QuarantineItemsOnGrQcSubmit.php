<?php

declare(strict_types=1);

namespace App\Listeners\QC;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\QC\Services\QuarantineService;
use App\Events\Procurement\GoodsReceiptSubmittedForQc;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-quarantine IQC-required items when a GR is submitted for QC.
 *
 * When a GR enters pending_qc, items with requires_iqc=true are moved from
 * the receiving location to the QC-HOLD quarantine location. This ensures
 * uninspected stock is not available for production or delivery.
 *
 * On QC pass, the UpdateGrOnInspectionResult listener triggers confirmation
 * which moves stock to the main warehouse via the normal ThreeWayMatch flow.
 */
class QuarantineItemsOnGrQcSubmit
{
    public function __construct(
        private readonly QuarantineService $quarantineService,
    ) {}

    public function handle(GoodsReceiptSubmittedForQc $event): void
    {
        $gr = $event->goodsReceipt;
        $gr->loadMissing(['items.itemMaster', 'receivedBy']);

        $actor = $gr->submittedForQcBy ?? $gr->receivedBy;
        if ($actor === null) {
            Log::warning('[QC-Quarantine] No actor found for quarantine on GR submit', ['gr_id' => $gr->id]);

            return;
        }

        // Find the receiving location (same logic as UpdateStockOnThreeWayMatch)
        $receivingLocationId = DB::table('warehouse_locations')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN LOWER(name) LIKE '%receiv%' OR LOWER(code) LIKE '%recv%' THEN 0 ELSE 1 END")
            ->value('id');

        if ($receivingLocationId === null) {
            Log::info('[QC-Quarantine] No receiving location found — skipping quarantine', ['gr_id' => $gr->id]);

            return;
        }

        $quarantinedCount = 0;

        foreach ($gr->items as $grItem) {
            if ($grItem->item_master_id === null) {
                continue;
            }

            $item = $grItem->itemMaster ?? ItemMaster::find($grItem->item_master_id);
            if ($item === null || ! $item->requires_iqc) {
                continue;
            }

            // Check if stock exists at the receiving location before quarantining
            $stockExists = DB::table('stock_balances')
                ->where('item_id', $grItem->item_master_id)
                ->where('location_id', $receivingLocationId)
                ->where('quantity', '>', 0)
                ->exists();

            if (! $stockExists) {
                Log::info('[QC-Quarantine] No stock at receiving location for item — skipping', [
                    'gr_id' => $gr->id,
                    'item_id' => $grItem->item_master_id,
                ]);

                continue;
            }

            try {
                $this->quarantineService->quarantine(
                    itemId: $grItem->item_master_id,
                    sourceLocationId: $receivingLocationId,
                    quantity: (float) $grItem->quantity_received,
                    referenceType: 'goods_receipt',
                    referenceId: $gr->id,
                    actor: $actor,
                    reason: "IQC pending — GR {$gr->gr_reference}",
                );

                $quarantinedCount++;
            } catch (\Throwable $e) {
                // Don't fail the QC submission if quarantine fails
                // Stock may not exist yet (GR not yet confirmed)
                Log::info('[QC-Quarantine] Could not quarantine item (stock may not exist yet)', [
                    'gr_id' => $gr->id,
                    'item_id' => $grItem->item_master_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($quarantinedCount > 0) {
            Log::info('[QC-Quarantine] Quarantined items for IQC', [
                'gr_id' => $gr->id,
                'count' => $quarantinedCount,
            ]);
        }
    }
}
