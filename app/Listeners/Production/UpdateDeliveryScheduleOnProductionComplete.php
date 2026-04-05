<?php

declare(strict_types=1);

namespace App\Listeners\Production;

use App\Domains\Production\Models\DeliverySchedule;
use App\Domains\Production\Models\ProductionOrder;
use App\Events\Production\ProductionOrderCompleted;
use Illuminate\Support\Facades\Log;

/**
 * When a Production Order completes, check if ALL POs linked to the
 * same Delivery Schedule are completed. If so, transition the DS
 * (and its items) from in_production -> ready.
 *
 * This closes the gap where DS status was never auto-updated after
 * production finished, requiring manual intervention.
 *
 * Auto-discovered by Laravel (in app/Listeners/).
 */
class UpdateDeliveryScheduleOnProductionComplete
{
    public function handle(ProductionOrderCompleted $event): void
    {
        $order = $event->order;

        if ($order->delivery_schedule_id === null) {
            return; // Not linked to a delivery schedule
        }

        $ds = DeliverySchedule::find($order->delivery_schedule_id);
        if ($ds === null) {
            return;
        }

        // Only update DS in production-related statuses
        if (! in_array($ds->status, ['in_production', 'open', 'planning'], true)) {
            return;
        }

        try {
            // Check if ALL production orders for this DS are completed
            $allPOs = ProductionOrder::where('delivery_schedule_id', $ds->id)
                ->whereNotIn('status', ['cancelled'])
                ->get();

            $totalPOs = $allPOs->count();
            $completedPOs = $allPOs->whereIn('status', ['completed', 'closed'])->count();

            if ($totalPOs === 0) {
                return;
            }

            if ($completedPOs >= $totalPOs) {
                // All production orders completed -> mark DS as ready
                $ds->update(['status' => 'ready']);

                // Also update DSI items to ready if multi-item support exists
                if (method_exists($ds, 'items')) {
                    $ds->items()
                        ->where('status', 'in_production')
                        ->update(['status' => 'ready']);

                    if (method_exists($ds, 'updateItemStatusSummary')) {
                        $ds->updateItemStatusSummary();
                    }
                }

                Log::info('[DS] Delivery Schedule auto-transitioned to ready - all POs completed', [
                    'delivery_schedule_id' => $ds->id,
                    'ds_reference' => $ds->ds_reference,
                    'completed_pos' => $completedPOs,
                    'total_pos' => $totalPOs,
                ]);
            } else {
                Log::info('[DS] PO completed but DS still has pending POs', [
                    'delivery_schedule_id' => $ds->id,
                    'completed_pos' => $completedPOs,
                    'total_pos' => $totalPOs,
                    'remaining' => $totalPOs - $completedPOs,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[DS] Failed to update DS status on PO completion', [
                'delivery_schedule_id' => $ds->id,
                'production_order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
