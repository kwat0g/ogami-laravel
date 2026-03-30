<?php

declare(strict_types=1);

namespace App\Listeners\Production;

use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\QC\Models\Inspection;
use App\Events\QC\InspectionPassed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * When an IQC (incoming quality control) inspection passes, check if any
 * released or on_hold production orders are waiting for that material.
 * Logs an informational message so production planners are aware that
 * materials are now available.
 *
 * MISSING-2 fix: Bridge between Procurement/QC and Production for
 * material readiness awareness.
 */
final class NotifyProductionOnMaterialArrival implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(InspectionPassed $event): void
    {
        $inspection = $event->inspection;

        // Only handle IQC inspections (incoming material quality checks)
        if ($inspection->stage !== 'iqc') {
            return;
        }

        $itemId = $inspection->item_master_id;
        if ($itemId === null) {
            return;
        }

        // Find production orders that are waiting for this material
        // (released = waiting for MRQ fulfillment, on_hold = possibly material shortage)
        $waitingOrders = ProductionOrder::query()
            ->whereIn('status', ['released', 'on_hold'])
            ->whereHas('bom', function ($q) use ($itemId): void {
                $q->whereHas('components', function ($q2) use ($itemId): void {
                    $q2->where('component_item_id', $itemId);
                });
            })
            ->get(['id', 'po_reference', 'status', 'product_item_id']);

        if ($waitingOrders->isEmpty()) {
            return;
        }

        $orderRefs = $waitingOrders->pluck('po_reference')->implode(', ');
        Log::info("[Production] Material arrived and passed IQC — item #{$itemId} needed by: {$orderRefs}", [
            'item_master_id' => $itemId,
            'inspection_id' => $inspection->id,
            'waiting_orders' => $waitingOrders->pluck('id')->all(),
        ]);

        // Check if any pending MRQs for these orders can now be fulfilled
        $orderIds = $waitingOrders->pluck('id')->all();
        $pendingMrqs = MaterialRequisition::query()
            ->whereIn('production_order_id', $orderIds)
            ->whereIn('status', ['submitted', 'approved'])
            ->get(['id', 'production_order_id', 'status']);

        if ($pendingMrqs->isNotEmpty()) {
            $mrqIds = $pendingMrqs->pluck('id')->implode(', ');
            Log::info("[Production] Pending MRQs that may now be fulfillable: #{$mrqIds}", [
                'mrq_ids' => $pendingMrqs->pluck('id')->all(),
            ]);
        }
    }
}
