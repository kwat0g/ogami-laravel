<?php

declare(strict_types=1);

namespace App\Listeners\Delivery;

use App\Domains\Delivery\Services\DeliveryService;
use App\Events\Production\ProductionOrderCompleted;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Creates a draft outbound Delivery Receipt when a Production Order completes,
 * provided the order is linked to a Delivery Schedule (i.e. a customer order).
 *
 * PROD-DEL-001: Completing a WO with a delivery schedule initiates the
 *               outbound delivery receipt workflow for warehouse staff.
 */
final class CreateDeliveryReceiptOnProductionComplete implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $uniqueFor = 60;

    public function uniqueId(ProductionOrderCompleted $event): string
    {
        return 'prod-dr-'.$event->order->id;
    }

    public function __construct(private readonly DeliveryService $deliveryService) {}

    public function handle(ProductionOrderCompleted $event): void
    {
        $order = $event->order;

        // Only auto-create DR if this WO is linked to a delivery schedule
        if ($order->delivery_schedule_id === null) {
            return;
        }

        // ── QC Gate: If this WO has a delivery schedule, defer DR creation
        //    to CreateDeliveryReceiptOnOqcPass (runs after QC inspection passes).
        //    This ensures finished goods are QC-approved before shipping.
        //    Only create DR immediately for WOs WITHOUT a delivery schedule
        //    (i.e. manual / internal production not linked to client orders).
        if ($order->delivery_schedule_id !== null) {
            return;
        }

        // Resolve the system user for auto-created records
        $systemUser = User::where('email', 'admin@ogamierp.local')->first();

        if ($systemUser === null) {
            return;
        }

        // Load delivery schedule to get customer
        $schedule = $order->deliverySchedule()->first();
        $customerId = $schedule?->customer_id;

        $netQty = max(0.0, (float) $order->qty_produced - (float) $order->qty_rejected);

        $receipt = $this->deliveryService->storeReceipt(
            data: [
                'direction' => 'outbound',
                'customer_id' => $customerId,
                'delivery_schedule_id' => $order->delivery_schedule_id,
                'receipt_date' => now()->toDateString(),
                'remarks' => "Auto-created from Production WO #{$order->id} ({$order->po_reference}) — pending warehouse confirmation.",
                'received_by_id' => $systemUser->id,
                'items' => [
                    [
                        'item_master_id' => $order->product_item_id,
                        'quantity_expected' => $netQty,
                        'quantity_received' => $netQty,
                        'remarks' => "Finished goods from WO {$order->po_reference}",
                    ],
                ],
            ],
            userId: $systemUser->id,
        );
    }
}
