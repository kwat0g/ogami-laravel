<?php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Sales\Models\SalesOrder;
use App\Events\Production\ProductionOrderCompleted;
use Illuminate\Support\Facades\Log;

/**
 * When ALL production orders linked to a sales order are completed,
 * advances the sales order status from in_production -> delivered.
 *
 * Mirrors UpdateClientOrderOnProductionComplete but for the Sales domain.
 */
class UpdateSalesOrderOnProductionComplete
{
    public function handle(ProductionOrderCompleted $event): void
    {
        $order = $event->order;

        if ($order->sales_order_id === null) {
            return;
        }

        $salesOrder = SalesOrder::find($order->sales_order_id);
        if ($salesOrder === null) {
            return;
        }

        if ($salesOrder->status !== 'in_production') {
            return;
        }

        try {
            $totalOrders = ProductionOrder::where('sales_order_id', $salesOrder->id)->count();
            $completedOrders = ProductionOrder::where('sales_order_id', $salesOrder->id)
                ->where('status', 'completed')
                ->count();

            if ($completedOrders >= $totalOrders && $totalOrders > 0) {
                // Transition to ready state -- actual 'delivered' should come from the delivery flow
                $targetStatus = in_array($salesOrder->status, ['confirmed', 'in_production'], true)
                    ? 'partially_delivered'
                    : $salesOrder->status;
                // If all production is done, mark ready for delivery (not delivered -- goods haven't shipped)
                if ($salesOrder->status === 'in_production') {
                    $targetStatus = 'partially_delivered';
                }
                $salesOrder->update(['status' => $targetStatus]);
                Log::info('[Sales] Sales order production complete -- marked partially_delivered (awaiting physical delivery)', [
                    'sales_order_id' => $salesOrder->id,
                    'total_orders' => $totalOrders,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[Sales] Failed to update sales order on production complete', [
                'sales_order_id' => $salesOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
