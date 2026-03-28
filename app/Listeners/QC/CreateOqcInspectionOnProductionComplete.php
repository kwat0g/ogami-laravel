<?php

declare(strict_types=1);

namespace App\Listeners\QC;

use App\Domains\QC\Models\Inspection;
use App\Events\Production\ProductionOrderCompleted;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Auto-creates an OQC (Outgoing Quality Control) inspection when a
 * Production Order completes, so QC must approve finished goods before delivery.
 *
 * QC-OQC-001: Production completion triggers mandatory outgoing inspection.
 */
final class CreateOqcInspectionOnProductionComplete implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public function handle(ProductionOrderCompleted $event): void
    {
        $order = $event->order;

        // Only create OQC if the WO produced output
        $netQty = (float) $order->qty_produced - (float) $order->qty_rejected;
        if ($netQty <= 0) {
            return;
        }

        // Check if an OQC inspection already exists for this WO (avoid duplicates)
        $existingOqc = Inspection::where('production_order_id', $order->id)
            ->where('stage', 'oqc')
            ->whereNotIn('status', ['voided'])
            ->exists();

        if ($existingOqc) {
            Log::info("QC-OQC-001: OQC inspection already exists for WO #{$order->id}, skipping auto-creation.");

            return;
        }

        $systemUser = User::where('email', config('ogami.system_user_email', 'admin@ogamierp.local'))->first();

        if ($systemUser === null) {
            Log::warning("QC-OQC-001: System user not found, cannot auto-create OQC for WO #{$order->id}");

            return;
        }

        Inspection::create([
            'stage' => 'oqc',
            'production_order_id' => $order->id,
            'item_master_id' => $order->product_item_id,
            'qty_inspected' => $netQty,
            'qty_passed' => 0,
            'qty_failed' => 0,
            'inspection_date' => now()->toDateString(),
            'remarks' => "Auto-created OQC for WO {$order->po_reference} — {$netQty} units pending inspection before delivery.",
            'created_by_id' => $systemUser->id,
        ]);

        Log::info("QC-OQC-001: Auto-created OQC inspection for WO #{$order->id} ({$netQty} units).");
    }
}
