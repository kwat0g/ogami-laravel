<?php

declare(strict_types=1);

namespace App\Listeners\Production;

use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Services\ProductionOrderService;
use App\Events\QC\InspectionFailed;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Auto-creates a rework Production Order when an OQC inspection fails and the
 * WO is linked to a delivery schedule (client order). The rework WO covers
 * the deficit between qty_required and qty_passed, so the full client order
 * quantity can still be met.
 *
 * QC-REWORK-001: OQC failure triggers rework production for deficit quantity.
 */
final class CreateReworkOrderOnOqcFail implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public function handle(InspectionFailed $event): void
    {
        $inspection = $event->inspection;

        // Only handle OQC inspections linked to a production order
        if ($inspection->stage !== 'oqc' || $inspection->production_order_id === null) {
            return;
        }

        $order = ProductionOrder::find($inspection->production_order_id);
        if ($order === null || $order->delivery_schedule_id === null) {
            return;
        }

        // Calculate deficit: what was required minus what passed QC
        $qtyPassed = (float) $inspection->qty_passed;
        $qtyRequired = (float) $order->qty_required;
        $deficit = $qtyRequired - $qtyPassed;

        if ($deficit <= 0) {
            Log::info("QC-REWORK-001: No deficit for WO #{$order->id} — {$qtyPassed} passed >= {$qtyRequired} required.");

            return;
        }

        // Check if a rework WO already exists for this inspection
        $existingRework = ProductionOrder::where('delivery_schedule_id', $order->delivery_schedule_id)
            ->where('notes', 'LIKE', "%rework from OQC #{$inspection->id}%")
            ->exists();

        if ($existingRework) {
            Log::info("QC-REWORK-001: Rework WO already exists for OQC #{$inspection->id}, skipping.");

            return;
        }

        // Calculate target dates from BOM
        $bom = BillOfMaterials::where('product_item_id', $order->product_item_id)
            ->where('is_active', true)
            ->first();

        $productionDays = max(1, $bom?->standard_production_days ?? 7);
        $targetStartDate = now()->addDay();
        $targetEndDate = $targetStartDate->copy()->addDays($productionDays - 1);

        // Delegate to ProductionOrderService::store() for consistent
        // po_reference generation, BOM snapshot, and cost estimation.
        $actor = User::find($order->created_by_id)
            ?? User::where('email', config('ogami.system_user_email', 'admin@ogamierp.local'))->first();

        if ($actor === null) {
            Log::warning("QC-REWORK-001: No user found to create rework order for WO #{$order->id}");
            return;
        }

        $poService = app(ProductionOrderService::class);
        $reworkOrder = $poService->store([
            'delivery_schedule_id' => $order->delivery_schedule_id,
            'client_order_id' => $order->client_order_id,
            'source_type' => 'rework',
            'source_id' => $order->id,
            'product_item_id' => $order->product_item_id,
            'bom_id' => $bom?->id ?? $order->bom_id,
            'qty_required' => $deficit,
            'target_start_date' => $targetStartDate->toDateString(),
            'target_end_date' => $targetEndDate->toDateString(),
            'notes' => "Rework order — rework from OQC #{$inspection->id} (WO {$order->po_reference}). "
                ."{$inspection->qty_failed} units failed inspection; producing {$deficit} to meet required qty of {$qtyRequired}.",
        ], $actor);

        Log::info("QC-REWORK-001: Created rework WO #{$reworkOrder->id} for {$deficit} units (OQC #{$inspection->id}, original WO #{$order->id}).");
    }
}
