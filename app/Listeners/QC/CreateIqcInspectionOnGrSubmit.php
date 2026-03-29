<?php

declare(strict_types=1);

namespace App\Listeners\QC;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Procurement\Services\GoodsReceiptService;
use App\Domains\QC\Services\InspectionService;
use App\Events\Procurement\GoodsReceiptSubmittedForQc;
use Illuminate\Support\Facades\Log;

/**
 * Auto-create IQC inspection records when a GR is submitted for QC.
 *
 * For each GR line item where the linked ItemMaster has requires_iqc = true,
 * creates an open IQC inspection linked to the GR.
 *
 * If no items require IQC, auto-transitions the GR to qc_passed immediately
 * (all items are treated as passing by default).
 */
class CreateIqcInspectionOnGrSubmit
{
    public function __construct(
        private readonly InspectionService $inspectionService,
    ) {}

    public function handle(GoodsReceiptSubmittedForQc $event): void
    {
        $gr = $event->goodsReceipt;
        $gr->loadMissing(['items.itemMaster']);

        $iqcItemsCreated = 0;

        foreach ($gr->items as $grItem) {
            $item = $grItem->itemMaster;
            if ($item === null && $grItem->item_master_id !== null) {
                $item = ItemMaster::find($grItem->item_master_id);
            }

            if ($item === null || ! $item->requires_iqc) {
                // Mark non-IQC items as passed immediately
                $grItem->update([
                    'qc_status' => 'passed',
                    'quantity_accepted' => $grItem->quantity_received,
                    'quantity_rejected' => 0,
                ]);

                continue;
            }

            try {
                $this->inspectionService->store([
                    'stage' => 'iqc',
                    'goods_receipt_id' => $gr->id,
                    'item_master_id' => $item->id,
                    'qty_inspected' => (float) $grItem->quantity_received,
                    'inspection_date' => now()->toDateString(),
                    'remarks' => "Auto-created IQC for GR {$gr->gr_reference}, item: {$item->name}",
                ], $gr->submitted_for_qc_by_id ?? $gr->received_by_id);

                $iqcItemsCreated++;
            } catch (\Throwable $e) {
                Log::error('[QC] Failed to auto-create IQC inspection', [
                    'gr_id' => $gr->id,
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // If no items require IQC, auto-pass the GR through QC
        if ($iqcItemsCreated === 0) {
            try {
                $grService = app(GoodsReceiptService::class);
                $actor = $gr->submittedForQcBy ?? $gr->receivedBy;
                if ($actor !== null) {
                    $grService->markQcPassed($gr->fresh(), $actor);
                    Log::info('[QC] GR auto-passed QC — no items require IQC', ['gr_id' => $gr->id]);
                }
            } catch (\Throwable $e) {
                Log::error('[QC] Failed to auto-pass GR through QC', [
                    'gr_id' => $gr->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('[QC] Created IQC inspections for GR', [
                'gr_id' => $gr->id,
                'inspections_created' => $iqcItemsCreated,
            ]);
        }
    }
}
