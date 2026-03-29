<?php

declare(strict_types=1);

namespace App\Listeners\Procurement;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Services\GoodsReceiptService;
use App\Domains\QC\Models\Inspection;
use Illuminate\Support\Facades\Log;

/**
 * Bridges QC inspection results back to the Goods Receipt workflow.
 *
 * When an IQC inspection linked to a GR is completed (passed or failed),
 * this listener checks if all IQC inspections for the GR are resolved
 * and auto-transitions the GR status accordingly:
 *
 *   - All passed -> markQcPassed()
 *   - Any failed -> markQcFailed()
 *
 * Handles both InspectionPassed and InspectionFailed events.
 */
class UpdateGrOnInspectionResult
{
    public function __construct(
        private readonly GoodsReceiptService $grService,
    ) {}

    /**
     * Handle both InspectionPassed and InspectionFailed events.
     * The event classes both have a public $inspection property.
     */
    public function handle(object $event): void
    {
        /** @var Inspection $inspection */
        $inspection = $event->inspection;

        // Only handle IQC inspections linked to a GR
        if ($inspection->stage !== 'iqc' || $inspection->goods_receipt_id === null) {
            return;
        }

        $gr = GoodsReceipt::find($inspection->goods_receipt_id);
        if ($gr === null || $gr->status !== 'pending_qc') {
            return;
        }

        // Update the corresponding GR item's QC status
        $this->updateGrItemQcStatus($gr, $inspection);

        // Check if all IQC inspections for this GR are resolved
        $allInspections = Inspection::where('goods_receipt_id', $gr->id)
            ->where('stage', 'iqc')
            ->get();

        $openInspections = $allInspections->filter(fn ($i) => $i->status === 'open');
        if ($openInspections->isNotEmpty()) {
            Log::info('[GR-QC] Waiting for remaining IQC inspections', [
                'gr_id' => $gr->id,
                'open_count' => $openInspections->count(),
            ]);

            return; // Still have open inspections — wait for all to complete
        }

        $failedInspections = $allInspections->filter(fn ($i) => $i->status === 'failed');

        try {
            // Resolve the actor: use the inspector or the QC submitter
            $actor = $inspection->inspector
                ?? ($gr->submittedForQcBy ?? $gr->receivedBy);

            if ($actor === null) {
                Log::error('[GR-QC] Cannot resolve actor for GR QC transition', ['gr_id' => $gr->id]);

                return;
            }

            $passedInspections = $allInspections->filter(fn ($i) => $i->status === 'passed');

            if ($failedInspections->isEmpty()) {
                // All inspections passed
                $this->grService->markQcPassed($gr->fresh(), $actor);
                Log::info('[GR-QC] All IQC inspections passed — GR moved to qc_passed', ['gr_id' => $gr->id]);
            } elseif ($passedInspections->isNotEmpty() && $failedInspections->isNotEmpty()) {
                // Mixed results: some items passed, some failed.
                // Auto-set accepted quantities for passed items so they are not blocked.
                // The GR still moves to qc_failed, but passed items already have
                // quantity_accepted set — the accept-with-defects flow only needs
                // to handle the failed items.
                foreach ($gr->fresh()->items as $grItem) {
                    if ($grItem->qc_status === 'passed' && $grItem->quantity_accepted === null) {
                        $grItem->update([
                            'quantity_accepted' => $grItem->quantity_received,
                            'quantity_rejected' => 0,
                        ]);
                    }
                }

                $failedItems = $failedInspections->map(fn ($i) => $i->itemMaster?->name ?? "Item #{$i->item_master_id}")->implode(', ');
                $passedItems = $passedInspections->map(fn ($i) => $i->itemMaster?->name ?? "Item #{$i->item_master_id}")->implode(', ');
                $notes = "Mixed QC result — Passed: {$passedItems}. Failed: {$failedItems}. Use 'Accept with Defects' to disposition failed items while keeping passed items.";
                $this->grService->markQcFailed($gr->fresh(), $actor, $notes);
                Log::info('[GR-QC] Mixed IQC results — GR moved to qc_failed with passed items pre-accepted', [
                    'gr_id' => $gr->id,
                    'passed_items' => $passedItems,
                    'failed_items' => $failedItems,
                ]);
            } else {
                // All inspections failed
                $failedItems = $failedInspections->map(fn ($i) => $i->itemMaster?->name ?? "Item #{$i->item_master_id}")->implode(', ');
                $this->grService->markQcFailed($gr->fresh(), $actor, "IQC failed for: {$failedItems}");
                Log::info('[GR-QC] IQC inspection failed — GR moved to qc_failed', [
                    'gr_id' => $gr->id,
                    'failed_items' => $failedItems,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[GR-QC] Failed to auto-transition GR on inspection result', [
                'gr_id' => $gr->id,
                'inspection_id' => $inspection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update the GR line item's QC status based on the inspection result.
     */
    private function updateGrItemQcStatus(GoodsReceipt $gr, Inspection $inspection): void
    {
        if ($inspection->item_master_id === null) {
            return;
        }

        $grItem = $gr->items()
            ->where('item_master_id', $inspection->item_master_id)
            ->first();

        if ($grItem === null) {
            return;
        }

        if ($inspection->status === 'passed') {
            $grItem->update([
                'qc_status' => 'passed',
                'quantity_accepted' => $grItem->quantity_received,
                'quantity_rejected' => 0,
            ]);
        } elseif ($inspection->status === 'failed') {
            $qtyFailed = (float) $inspection->qty_failed;
            $qtyPassed = (float) $inspection->qty_passed;
            $totalReceived = (float) $grItem->quantity_received;

            $grItem->update([
                'qc_status' => 'failed',
                'quantity_accepted' => min($qtyPassed, $totalReceived),
                'quantity_rejected' => min($qtyFailed, $totalReceived),
            ]);
        }
    }
}
