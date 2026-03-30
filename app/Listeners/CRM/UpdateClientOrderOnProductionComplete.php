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
            $totalOrders = ProductionOrder::where('client_order_id', $clientOrder->id)->count();
            $completedOrders = ProductionOrder::where('client_order_id', $clientOrder->id)
                ->where('status', 'completed')
                ->count();

            if ($completedOrders >= $totalOrders && $totalOrders > 0) {
                // All production done -> ready for delivery
                $clientOrder->update(['status' => 'ready_for_delivery']);
                Log::info('[CRM] Client order ready for delivery - all production completed', [
                    'client_order_id' => $clientOrder->id,
                    'total_orders' => $totalOrders,
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
