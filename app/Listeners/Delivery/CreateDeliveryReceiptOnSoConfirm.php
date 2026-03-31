<?php

declare(strict_types=1);

namespace App\Listeners\Delivery;

use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Delivery\Services\DeliveryService;
use App\Events\Sales\SalesOrderConfirmed;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * CHAIN-SO-DR-001: Auto-creates a draft outbound Delivery Receipt
 * when a Sales Order is confirmed AND stock-available items exist.
 *
 * If the SO triggered production orders (make-to-order), DR creation
 * is deferred to the production-completion / OQC-pass listeners instead.
 * This listener only handles the stock-available (ready-to-ship) path.
 */
final class CreateDeliveryReceiptOnSoConfirm implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $uniqueFor = 60;

    public function uniqueId(SalesOrderConfirmed $event): string
    {
        return 'so-dr-'.$event->order->id;
    }

    public function __construct(private readonly DeliveryService $deliveryService) {}

    public function handle(SalesOrderConfirmed $event): void
    {
        $order = $event->order->loadMissing(['items.itemMaster', 'customer']);

        // Skip if the SO is in production (DR will be created on production completion)
        if ($order->status === 'in_production') {
            return;
        }

        // Skip if a non-cancelled DR already exists for this SO (idempotency)
        $existingDr = DeliveryReceipt::where('sales_order_id', $order->id)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($existingDr) {
            return;
        }

        // Build item list from SO items
        $drItems = [];
        foreach ($order->items as $soItem) {
            if ($soItem->quantity <= 0) {
                continue;
            }

            $drItems[] = [
                'item_master_id' => $soItem->item_master_id,
                'quantity_expected' => $soItem->quantity,
                'quantity_received' => 0,
                'remarks' => "From SO #{$order->order_number}, item: {$soItem->itemMaster?->name}",
            ];
        }

        if (empty($drItems)) {
            return;
        }

        $systemUser = User::where('email', config('ogami.system_user_email', 'admin@ogamierp.local'))->first();

        if ($systemUser === null) {
            Log::warning('CreateDeliveryReceiptOnSoConfirm: system user not found, cannot auto-create DR.');

            return;
        }

        try {
            $this->deliveryService->storeReceipt(
                data: [
                    'direction' => 'outbound',
                    'customer_id' => $order->customer_id,
                    'sales_order_id' => $order->id,
                    'receipt_date' => now()->toDateString(),
                    'remarks' => "Auto-created from confirmed Sales Order #{$order->order_number}.",
                    'received_by_id' => $systemUser->id,
                    'items' => $drItems,
                ],
                userId: $systemUser->id,
            );
        } catch (\Throwable $e) {
            Log::error('CreateDeliveryReceiptOnSoConfirm failed', [
                'sales_order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
