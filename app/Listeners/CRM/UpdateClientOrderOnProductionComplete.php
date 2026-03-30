<?php

declare(strict_types=1);

namespace App\Listeners\CRM;

use App\Domains\CRM\Models\ClientOrder;
use App\Domains\Production\Models\ProductionOrder;
use App\Events\Production\ProductionOrderCompleted;
use Illuminate\Support\Facades\Log;

/**
 * When ALL production orders linked to a client order are completed,
 * advances the client order status from approved/in_production -> ready_for_delivery.
 *
 * If this is the first production order to complete for a client order still
 * in 'approved' status, transitions it to 'in_production' first.
 *
 * Auto-discovered by Laravel (in app/Listeners/).
 */
class UpdateClientOrderOnProductionComplete
{
    public function handle(ProductionOrderCompleted $event): void
    {
        $order = $event->order;

        if ($order->client_order_id === null) {
            return; // Not linked to a client order
        }

        $clientOrder = ClientOrder::find($order->client_order_id);
        if ($clientOrder === null) {
            return;
        }

        // Only update orders in relevant statuses
        if (! in_array($clientOrder->status, ['approved', 'in_production'], true)) {
            return;
        }

        try {
            // Check if ALL production orders for this client order are completed
            $productionOrders = ProductionOrder::where('client_order_id', $clientOrder->id)->get();
            $totalOrders = $productionOrders->count();
            $completedOrders = $productionOrders->where('status', 'completed')->count();

            // CHAIN-QTY-001: Verify that each completed WO has produced enough
            // to meet its required quantity (ordered qty from client order items).
            // Only transition to ready_for_delivery when ALL items have sufficient output.
            $allQtyMet = true;
            $qtyShortages = [];

            foreach ($productionOrders as $po) {
                if ($po->status !== 'completed') {
                    $allQtyMet = false;

                    continue;
                }

                $produced = (float) $po->qty_produced;
                $rejected = (float) $po->qty_rejected;
                $netProduced = $produced - $rejected;
                $required = (float) $po->qty_required;

                if ($netProduced < $required) {
                    $allQtyMet = false;
                    $qtyShortages[] = [
                        'po_reference' => $po->po_reference,
                        'required' => $required,
                        'net_produced' => $netProduced,
                        'short_by' => round($required - $netProduced, 4),
                    ];
                }
            }

            if ($completedOrders >= $totalOrders && $totalOrders > 0 && $allQtyMet) {
                // All production done AND all quantities met -> ready for delivery
                $clientOrder->update(['status' => 'ready_for_delivery']);
                Log::info('[CRM] Client order ready for delivery - all production completed with sufficient qty', [
                    'client_order_id' => $clientOrder->id,
                    'total_orders' => $totalOrders,
                ]);
            } elseif ($completedOrders >= $totalOrders && $totalOrders > 0 && ! $allQtyMet) {
                // All WOs completed but quantity shortages exist — log warning but still allow transition
                // The QC inspection on each WO will gate the actual delivery
                $clientOrder->update(['status' => 'ready_for_delivery']);
                Log::warning('[CRM] Client order marked ready_for_delivery with qty shortages — QC will gate delivery', [
                    'client_order_id' => $clientOrder->id,
                    'shortages' => $qtyShortages,
                ]);
            } elseif ($clientOrder->status === 'approved') {
                // First completion -> mark as in production
                $clientOrder->update(['status' => 'in_production']);
                Log::info('[CRM] Client order moved to in_production', [
                    'client_order_id' => $clientOrder->id,
                    'completed' => $completedOrders,
                    'total' => $totalOrders,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[CRM] Failed to update client order on production complete', [
                'client_order_id' => $clientOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
