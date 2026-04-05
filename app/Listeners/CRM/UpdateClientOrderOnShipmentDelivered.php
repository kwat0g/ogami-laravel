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

            // Check if AR invoice has been created for any DR tied to this client order.
            // customer_invoices does not have client_order_id; resolve through
            // delivery_receipts -> delivery_schedules.
            $hasInvoice = DB::table('customer_invoices as ci')
                ->join('delivery_receipts as dr', 'dr.id', '=', 'ci.delivery_receipt_id')
                ->leftJoin('delivery_schedules as ds_by_id', 'ds_by_id.id', '=', 'dr.delivery_schedule_id')
                ->leftJoin('delivery_schedules as ds_by_dr', 'ds_by_dr.delivery_receipt_id', '=', 'dr.id')
                ->whereNull('ci.deleted_at')
                ->where(function ($query) use ($clientOrder): void {
                    $query->where('ds_by_id.client_order_id', $clientOrder->id)
                        ->orWhere('ds_by_dr.client_order_id', $clientOrder->id);
                })
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
        // Path 0: direct link via delivery_schedules.id (preferred, current schema).
        if ($receipt->delivery_schedule_id) {
            $clientOrderId = DB::table('delivery_schedules')
                ->where('id', $receipt->delivery_schedule_id)
                ->value('client_order_id');

            if ($clientOrderId) {
                return (int) $clientOrderId;
            }
        }

        // Path 1: delivery_schedule -> combined_delivery_schedules -> client_order_id
        if ($receipt->delivery_schedule_id) {
            $clientOrderId = DB::table('combined_delivery_schedules')
                ->where('id', $receipt->delivery_schedule_id)
                ->value('client_order_id');

            if ($clientOrderId) {
                return (int) $clientOrderId;
            }
        }

        // Path 2: resolve through delivery_schedules by delivery_receipt_id.
        // This is used when delivery_receipts.delivery_schedule_id is null.
        $clientOrderId = DB::table('delivery_schedules')
            ->where('delivery_receipt_id', $receipt->id)
            ->value('client_order_id');

        if ($clientOrderId) {
            return (int) $clientOrderId;
        }

        // Path 3: last-resort lookup through invoice->delivery_receipt->delivery_schedule.
        $clientOrderId = DB::table('customer_invoices')
            ->where('delivery_receipt_id', $receipt->id)
            ->whereNull('deleted_at')
            ->value('delivery_receipt_id');

        if (! $clientOrderId) {
            return null;
        }

        $resolvedClientOrderId = DB::table('delivery_schedules')
            ->where('delivery_receipt_id', (int) $clientOrderId)
            ->value('client_order_id');

        return $resolvedClientOrderId ? (int) $resolvedClientOrderId : null;
    }
}
