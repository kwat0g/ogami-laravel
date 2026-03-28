<?php

declare(strict_types=1);

namespace App\Listeners\Delivery;

use App\Domains\Delivery\Services\DeliveryService;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\QC\Models\Inspection;
use App\Events\QC\InspectionPassed;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Creates a Delivery Receipt when an OQC inspection passes, using qty_passed
 * (not qty_produced) so only QC-approved goods proceed to delivery.
 *
 * QC-DEL-001: Delivery receipt gated behind OQC pass.
 */
final class CreateDeliveryReceiptOnOqcPass implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public function __construct(
        private readonly DeliveryService $deliveryService,
    ) {}

    public function handle(InspectionPassed $event): void
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

        $qtyPassed = (float) $inspection->qty_passed;
        if ($qtyPassed <= 0) {
            return;
        }

        $systemUser = User::where('email', config('ogami.system_user_email', 'admin@ogamierp.local'))->first();
        if ($systemUser === null) {
            Log::warning("QC-DEL-001: System user not found, cannot create DR for OQC #{$inspection->id}");

            return;
        }

        $schedule = $order->deliverySchedule()->first();
        $customerId = $schedule?->customer_id;

        try {
            $this->deliveryService->storeReceipt(
                data: [
                    'direction' => 'outbound',
                    'customer_id' => $customerId,
                    'delivery_schedule_id' => $order->delivery_schedule_id,
                    'receipt_date' => now()->toDateString(),
                    'remarks' => "Auto-created from OQC inspection #{$inspection->id} (WO {$order->po_reference}) — {$qtyPassed} QC-approved units.",
                    'received_by_id' => $systemUser->id,
                    'items' => [
                        [
                            'item_master_id' => $order->product_item_id,
                            'quantity_expected' => $qtyPassed,
                            'quantity_received' => $qtyPassed,
                            'remarks' => "QC-approved finished goods from WO {$order->po_reference}",
                        ],
                    ],
                ],
                userId: $systemUser->id,
            );

            // Update delivery schedule status if applicable
            if ($schedule !== null) {
                $schedule->update(['status' => 'ready']);
                if ($schedule->combined_delivery_schedule_id) {
                    $schedule->combinedDeliverySchedule?->updateItemStatusSummary();
                }
            }

            Log::info("QC-DEL-001: DR created from OQC #{$inspection->id} for WO #{$order->id} ({$qtyPassed} units).");
        } catch (\Throwable $e) {
            Log::error("QC-DEL-001: Failed to create DR from OQC #{$inspection->id}: {$e->getMessage()}");
        }
    }
}
