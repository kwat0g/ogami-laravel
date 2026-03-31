<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Production\Models\CombinedDeliverySchedule;
use App\Domains\Production\Models\DeliverySchedule;
use App\Models\User;
use App\Notifications\Delivery\DeliveryDisputeNotification;
use App\Notifications\Delivery\DeliveryScheduleDelayedNotification;
use App\Notifications\Delivery\DeliveryScheduleDispatchedNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class CombinedDeliveryScheduleService implements ServiceContract
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = CombinedDeliverySchedule::with('customer', 'clientOrder')
            ->orderByRaw("
                CASE status 
                    WHEN 'ready' THEN 1 
                    WHEN 'partially_ready' THEN 2
                    WHEN 'dispatched' THEN 3
                    WHEN 'planning' THEN 4
                    WHEN 'delivered' THEN 5
                    ELSE 6
                END
            ")
            ->orderBy('target_delivery_date', 'asc');

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('target_delivery_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('target_delivery_date', '<=', $filters['date_to']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Dispatch the combined delivery to customer
     */
    public function dispatch(
        CombinedDeliverySchedule $schedule,
        int $userId,
        ?int $vehicleId = null,
        ?string $driverName = null,
        ?string $deliveryNotes = null
    ): CombinedDeliverySchedule {
        // Validate schedule can be dispatched
        if (! in_array($schedule->status, [CombinedDeliverySchedule::STATUS_READY, CombinedDeliverySchedule::STATUS_PARTIALLY_READY], true)) {
            throw new DomainException(
                "Cannot dispatch schedule in status: {$schedule->status}. Must be 'ready' or 'partially_ready'.",
                'SCHEDULE_NOT_READY',
                422
            );
        }

        return DB::transaction(function () use ($schedule, $userId, $deliveryNotes): CombinedDeliverySchedule {
            // Update combined schedule
            $schedule->update([
                'status' => CombinedDeliverySchedule::STATUS_DISPATCHED,
                'dispatched_by_id' => $userId,
                'dispatched_at' => now(),
            ]);

            // Update all item schedules
            foreach ($schedule->itemSchedules as $itemSchedule) {
                if ($itemSchedule->status === 'ready') {
                    $itemSchedule->update([
                        'status' => 'dispatched',
                        'notes' => $itemSchedule->notes."\nDispatched: ".now()->toDateString().($deliveryNotes ? " - {$deliveryNotes}" : ''),
                    ]);
                }
            }

            // Create delivery receipt with valid state machine status
            $deliveryReceipt = DeliveryReceipt::create([
                'customer_id' => $schedule->customer_id,
                'delivery_schedule_id' => $schedule->itemSchedules->first()?->id,
                'direction' => 'outbound',
                'status' => 'draft',
                'receipt_date' => null,
                'remarks' => $deliveryNotes,
                'created_by_id' => $userId,
            ]);

            // Auto-confirm since items are ready for dispatch
            $deliveryReceipt->update(['status' => 'confirmed']);

            // Notify customer
            $schedule->customer->notify(DeliveryScheduleDispatchedNotification::fromModel($schedule, $deliveryReceipt));

            return $schedule->fresh();
        });
    }

    /**
     * Mark delivery as delivered by company (physical delivery)
     * This doesn't create invoice yet - client must confirm first
     */
    public function markDelivered(
        CombinedDeliverySchedule $schedule,
        string $deliveryDate,
        int $userId,
        ?string $receivedBy = null,
        ?string $deliveryReceiptNumber = null
    ): CombinedDeliverySchedule {
        if ($schedule->status !== CombinedDeliverySchedule::STATUS_DISPATCHED) {
            throw new DomainException(
                'Cannot mark as delivered. Schedule must be dispatched first.',
                'SCHEDULE_NOT_DISPATCHED',
                422
            );
        }

        return DB::transaction(function () use ($schedule, $deliveryDate): CombinedDeliverySchedule {
            // Update combined schedule - now awaiting client acknowledgment
            $schedule->update([
                'status' => CombinedDeliverySchedule::STATUS_DELIVERED,
                'actual_delivery_date' => $deliveryDate,
            ]);

            // Update all item schedules
            foreach ($schedule->itemSchedules as $itemSchedule) {
                $itemSchedule->update([
                    'status' => 'delivered',
                ]);
            }

            // Sync ClientOrder to delivered status
            $clientOrder = $schedule->clientOrder;
            if ($clientOrder && in_array($clientOrder->status, ['approved', 'in_production', 'ready_for_delivery', 'dispatched'], true)) {
                $clientOrder->update(['status' => 'delivered']);
            }

            // Note: Invoice is NOT created here - waits for client acknowledgment

            return $schedule->fresh();
        });
    }

    /**
     * Notify client about missing items with expected delivery date
     */
    public function notifyMissingItems(
        CombinedDeliverySchedule $schedule,
        array $missingItems,
        ?string $expectedDeliveryDate,
        ?string $message,
        int $userId
    ): void {
        // Update schedule with missing items info
        $itemSummary = $schedule->item_status_summary ?? [];

        foreach ($missingItems as $missingItem) {
            // Find the item in summary and mark as missing
            foreach ($itemSummary as &$item) {
                if ($item['delivery_schedule_id'] === $missingItem['item_id']) {
                    $item['is_missing'] = true;
                    $item['missing_reason'] = $missingItem['reason'];
                    $item['expected_delivery'] = $expectedDeliveryDate;
                }
            }
        }

        $schedule->update([
            'item_status_summary' => $itemSummary,
            'status' => CombinedDeliverySchedule::STATUS_PARTIALLY_READY,
        ]);

        // Send notification to customer
        $schedule->customer->notify(DeliveryScheduleDelayedNotification::fromModel(
            $schedule,
            $missingItems,
            $expectedDeliveryDate,
            $message
        ));
    }

    /**
     * Update combined schedule when an item becomes ready
     */
    public function updateWhenItemReady(DeliverySchedule $itemSchedule): void
    {
        $combinedSchedule = $itemSchedule->combinedDeliverySchedule;

        if (! $combinedSchedule) {
            return;
        }

        $combinedSchedule->updateItemStatusSummary();
    }

    /**
     * Create customer invoice from delivered order.
     * Resolves GL accounts from system_settings and current open FiscalPeriod.
     */
    private function createCustomerInvoice(
        CombinedDeliverySchedule $schedule,
        int $userId
    ): CustomerInvoice {
        $clientOrder = $schedule->clientOrder;

        // Idempotency: check if invoice already exists for this CDS reference
        $existingInvoice = CustomerInvoice::where('description', 'LIKE', "%{$schedule->cds_reference}%")
            ->where('customer_id', $schedule->customer_id)
            ->whereNotIn('status', ['cancelled'])
            ->first();
        if ($existingInvoice) {
            return $existingInvoice;
        }

        // Read GL account IDs from system_settings
        $arAccountId = (int) json_decode(
            DB::table('system_settings')->where('key', 'default_ar_account_id')->value('value') ?? 'null'
        );
        $revenueAccountId = (int) json_decode(
            DB::table('system_settings')->where('key', 'default_revenue_account_id')->value('value') ?? 'null'
        );

        if (! $arAccountId || ! $revenueAccountId) {
            throw new DomainException(
                'Auto-invoicing requires default_ar_account_id and default_revenue_account_id to be configured in System Settings.',
                'AUTO_INVOICE_GL_NOT_CONFIGURED',
                422
            );
        }

        // Resolve open fiscal period
        $fiscalPeriod = FiscalPeriod::where('status', 'open')->orderByDesc('start_date')->first();
        if (! $fiscalPeriod) {
            throw new DomainException(
                'No open fiscal period found. Please open a fiscal period before auto-invoicing.',
                'NO_OPEN_FISCAL_PERIOD',
                422
            );
        }

        // Calculate totals (12% VAT — rate read from system_settings via CustomerInvoice convention)
        $subtotal = $clientOrder->total_amount_centavos / 100;
        $vatAmount = round($subtotal * 0.12, 2);
        $total = $subtotal + $vatAmount;

        $invoice = CustomerInvoice::create([
            'customer_id' => $schedule->customer_id,
            'fiscal_period_id' => $fiscalPeriod->id,
            'ar_account_id' => $arAccountId,
            'revenue_account_id' => $revenueAccountId,
            'delivery_receipt_id' => null, // CDS is not a DeliveryReceipt; link when DR is created
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total_amount' => $total,
            'status' => 'draft',
            'description' => "Auto-invoice for Client Order {$clientOrder->order_reference} / {$schedule->cds_reference}",
            'created_by' => $userId,
        ]);

        // Create invoice lines from order items
        foreach ($clientOrder->items as $item) {
            $unitPrice = ($item->negotiated_price_centavos ?? $item->unit_price_centavos) / 100;
            $qty = $item->negotiated_quantity ?? $item->quantity;

            $invoice->lines()->create([
                'item_master_id' => $item->item_master_id,
                'description' => $item->item_description,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'amount' => round($unitPrice * $qty, 2),
            ]);
        }

        return $invoice;
    }

    /**
     * Client acknowledges receipt of delivery
     * AFTER acknowledgment, invoice is created
     */
    public function acknowledgeReceipt(
        CombinedDeliverySchedule $schedule,
        array $itemAcknowledgments,
        ?string $generalNotes,
        int $userId
    ): CombinedDeliverySchedule {
        // Only allow acknowledgment if status is delivered (physically delivered)
        if ($schedule->status !== CombinedDeliverySchedule::STATUS_DELIVERED) {
            throw new DomainException(
                'Cannot acknowledge. Delivery must be marked as delivered by company first.',
                'SCHEDULE_NOT_DELIVERED',
                422
            );
        }

        // Validate all items are accounted for
        $totalItems = $schedule->itemSchedules->count();
        if (count($itemAcknowledgments) !== $totalItems) {
            throw new DomainException(
                'All items must be acknowledged',
                'INCOMPLETE_ACKNOWLEDGMENT',
                422
            );
        }

        return DB::transaction(function () use ($schedule, $itemAcknowledgments, $userId): CombinedDeliverySchedule {
            $hasIssues = false;
            $disputeItems = [];

            foreach ($itemAcknowledgments as $ack) {
                $itemSchedule = DeliverySchedule::find($ack['item_id']);
                if (! $itemSchedule) {
                    continue;
                }

                // Store acknowledgment details
                $itemSchedule->update([
                    'client_acknowledgment' => [
                        'received_qty' => $ack['received_qty'],
                        'condition' => $ack['condition'], // 'good', 'damaged', 'missing'
                        'notes' => $ack['notes'] ?? null,
                        'acknowledged_at' => now()->toIso8601String(),
                        'acknowledged_by' => $userId,
                    ],
                ]);

                if ($ack['condition'] !== 'good') {
                    $hasIssues = true;
                    $disputeItems[] = [
                        'delivery_schedule_id' => $itemSchedule->id,
                        'item_master_id' => $itemSchedule->product_item_id,
                        'condition' => $ack['condition'],
                        'received_qty' => $ack['received_qty'],
                        'notes' => $ack['notes'] ?? null,
                    ];
                }
            }

            // Flag dispute if any item had issues
            if ($hasIssues) {
                $schedule->update([
                    'has_dispute' => true,
                    'dispute_summary' => $disputeItems,
                ]);

                // Auto-create a formal DeliveryDispute record
                try {
                    $reporter = User::find($userId);
                    if ($reporter) {
                        $disputeService = app(\App\Domains\Delivery\Services\DeliveryDisputeService::class);

                        // Map dispute items to the format expected by DeliveryDisputeService
                        $formattedItems = [];
                        foreach ($itemAcknowledgments as $ack) {
                            $itemSchedule = DeliverySchedule::find($ack['item_id']);
                            $formattedItems[] = [
                                'item_master_id' => $itemSchedule?->product_item_id ?? 0,
                                'expected_qty' => (float) ($itemSchedule?->qty_ordered ?? 0),
                                'received_qty' => (float) $ack['received_qty'],
                                'condition' => $ack['condition'],
                                'notes' => $ack['notes'] ?? null,
                                'photo_url' => $ack['photo_url'] ?? null,
                            ];
                        }

                        $disputeService->createFromAcknowledgment(
                            $schedule->deliverySchedules()->first() ?? DeliverySchedule::make(['customer_id' => $schedule->customer_id, 'client_order_id' => $schedule->client_order_id]),
                            $formattedItems,
                            $reporter,
                            $generalNotes,
                        );
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[CDS] Failed to auto-create delivery dispute', [
                        'schedule_id' => $schedule->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Notify sales team after commit
                DB::afterCommit(function () use ($schedule): void {
                    User::permission('sales.order_review')
                        ->each(fn (User $u) => $u->notify(DeliveryDisputeNotification::fromModel($schedule)));
                });
            }

            // Create invoice after client acknowledgment (only if no disputes)
            if (! $hasIssues) {
                $this->createCustomerInvoice($schedule, $userId);
            }

            // Update client order status to fulfilled ONLY if no disputes
            // When disputes exist, order stays at "delivered" until dispute is resolved
            $clientOrder = $schedule->clientOrder;
            if ($clientOrder && in_array($clientOrder->status, ['delivered', 'dispatched', 'ready_for_delivery'], true)) {
                if (! $hasIssues) {
                    $clientOrder->update(['status' => 'fulfilled']);
                }
                // If has issues: order stays at current status, dispute must be resolved first
            }

            return $schedule->fresh();
        });
    }
}
