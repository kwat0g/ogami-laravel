<?php

declare(strict_types=1);

namespace App\Listeners\CRM;

use App\Domains\CRM\Models\ClientOrder;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Events\Delivery\ShipmentDelivered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * When a shipment is delivered, traces it back to the client order
 * and updates the status to 'delivered' or 'fulfilled'.
 *
 * Chain: Shipment -> DeliveryReceipt -> CombinedDeliverySchedule -> ClientOrder
 *
 * Auto-discovered by Laravel (in app/Listeners/).
 */
class UpdateClientOrderOnShipmentDelivered
{
    public function handle(ShipmentDelivered $event): void
    {
        $shipment = $event->shipment;

        // Trace back to client order via delivery receipt
        $deliveryReceipt = $shipment->deliveryReceipt;
        if ($deliveryReceipt === null) {
            return;
        }

        // Try to find the client order through the delivery schedule chain
        $clientOrderId = $this->resolveClientOrderId($deliveryReceipt);
        if ($clientOrderId === null) {
            return;
        }

        $clientOrder = ClientOrder::find($clientOrderId);
        if ($clientOrder === null) {
            return;
        }

        // Only update orders in delivery-related statuses
        if (! in_array($clientOrder->status, ['approved', 'in_production', 'ready_for_delivery', 'dispatched', 'delivered'], true)) {
            return;
        }

        try {
            if (in_array($clientOrder->status, ['approved', 'in_production', 'ready_for_delivery', 'dispatched'], true)) {
                $clientOrder->update(['status' => 'delivered']);
                Log::info('[CRM] Client order marked as delivered', [
                    'client_order_id' => $clientOrder->id,
                    'shipment_id' => $shipment->id,
                ]);
            }

            // Check if AR invoice has been created (indicates fulfillment)
            $hasInvoice = DB::table('customer_invoices')
                ->where('client_order_id', $clientOrder->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasInvoice && $clientOrder->fresh()->status === 'delivered') {
                $clientOrder->update(['status' => 'fulfilled']);
                Log::info('[CRM] Client order fulfilled (delivered + invoiced)', [
                    'client_order_id' => $clientOrder->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[CRM] Failed to update client order on shipment delivered', [
                'client_order_id' => $clientOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveClientOrderId(DeliveryReceipt $receipt): ?int
    {
        // Path 1: delivery_schedule -> combined_delivery_schedules -> client_order_id
        if ($receipt->delivery_schedule_id) {
            $clientOrderId = DB::table('combined_delivery_schedules')
                ->where('id', $receipt->delivery_schedule_id)
                ->value('client_order_id');

            if ($clientOrderId) {
                return (int) $clientOrderId;
            }
        }

        // Path 2: Check if there's a production order linked via the AR invoice chain
        // delivery_receipt -> shipment -> ar_invoice -> client_order
        $clientOrderId = DB::table('customer_invoices')
            ->where('delivery_receipt_id', $receipt->id)
            ->whereNull('deleted_at')
            ->value('client_order_id');

        return $clientOrderId ? (int) $clientOrderId : null;
    }
}
