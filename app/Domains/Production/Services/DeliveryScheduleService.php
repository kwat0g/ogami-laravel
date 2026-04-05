<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Models\FiscalPeriod;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Delivery\Models\DeliveryReceiptItem;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\DeliverySchedule;
use App\Domains\Production\Models\DeliveryScheduleItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Models\User;
use App\Notifications\Delivery\DeliveryDisputeNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DeliveryScheduleService implements ServiceContract
{
    public function __construct(
        private readonly ProductionOrderService $poService,
        private readonly StockService $stockService,
    ) {}

    /**
     * @param  array<string,mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = DeliverySchedule::with('customer', 'productItem')
            ->orderBy('target_delivery_date');

        if ($filters['with_archived'] ?? false) {
            $query->withTrashed();
        }

        if ($filters['search'] ?? null) {
            $v = $filters['search'];
            $query->where(fn ($q) => $q->where('ds_reference', 'ilike', "%{$v}%")->orWhereHas('customer', fn ($q2) => $q2->where('name', 'ilike', "%{$v}%"))->orWhereHas('productItem', fn ($q2) => $q2->where('name', 'ilike', "%{$v}%")));
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['date_from'])) {
            $query->where('target_delivery_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('target_delivery_date', '<=', $filters['date_to']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data): DeliverySchedule
    {
        /** @var DeliverySchedule $ds */
        $ds = DeliverySchedule::create([
            'customer_id' => $data['customer_id'],
            'product_item_id' => $data['product_item_id'],
            'qty_ordered' => $data['qty_ordered'],
            'target_delivery_date' => $data['target_delivery_date'],
            'type' => $data['type'] ?? 'local',
            'notes' => $data['notes'] ?? null,
        ]);

        return $ds->load('customer', 'productItem');
    }

    /** @param array<string,mixed> $data */
    public function update(DeliverySchedule $ds, array $data): DeliverySchedule
    {
        $originalStatus = $ds->status;

        $ds->update(array_filter([
            'qty_ordered' => $data['qty_ordered'] ?? null,
            'target_delivery_date' => $data['target_delivery_date'] ?? null,
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));

        $refreshed = $ds->refresh();

        // Auto-create Production Order when Delivery Schedule is confirmed
        if ($originalStatus !== 'confirmed' && $refreshed->status === 'confirmed') {
            $this->maybeAutoCreateProductionOrder($refreshed);
        }

        return $refreshed;
    }

    /**
     * Check if Production Order should be auto-created for this Delivery Schedule.
     * For multi-item DS, iterates DSI items individually (not just the parent DS product).
     * Creates PO per item if:
     * 1. No existing PO linked to this DS item
     * 2. Stock is insufficient to fulfill the item
     * 3. BOM exists for the product
     */
    private function maybeAutoCreateProductionOrder(DeliverySchedule $ds): void
    {
        $systemUser = User::where('email', config('ogami.system_user_email', 'admin@ogamierp.local'))->first();
        if ($systemUser === null) {
            Log::error('DS-Auto-PO: System user not found, cannot auto-create PO');

            return;
        }

        // Multi-item DS: iterate DSI items individually
        $dsItems = $ds->items()->get();
        if ($dsItems->isNotEmpty()) {
            foreach ($dsItems as $dsItem) {
                // Skip if PO already exists for this specific DSI
                $existingPo = ProductionOrder::where('delivery_schedule_id', $ds->id)
                    ->where('delivery_schedule_item_id', $dsItem->id)
                    ->whereNotIn('status', ['cancelled', 'closed'])
                    ->exists();

                if ($existingPo) {
                    Log::info("DS-Auto-PO: PO already exists for DSI #{$dsItem->id} in DS {$ds->ds_reference}");

                    continue;
                }

                $availableStock = $this->getAvailableStock($dsItem->product_item_id);
                $requiredQty = (float) $dsItem->qty_ordered;

                if ($availableStock >= $requiredQty) {
                    Log::info("DS-Auto-PO: Sufficient stock ({$availableStock}) for DSI #{$dsItem->id}, skipping PO");

                    continue;
                }

                $bom = BillOfMaterials::where('product_item_id', $dsItem->product_item_id)
                    ->where('is_active', true)
                    ->latest('version')
                    ->first();

                if ($bom === null) {
                    Log::warning("DS-Auto-PO: No active BOM for product #{$dsItem->product_item_id} in DSI #{$dsItem->id}");

                    continue;
                }

                $qtyToProduce = $requiredQty - $availableStock;

                try {
                    $po = $this->poService->store([
                        'delivery_schedule_id' => $ds->id,
                        'delivery_schedule_item_id' => $dsItem->id,
                        'source_type' => 'delivery_schedule',
                        'source_id' => $ds->id,
                        'product_item_id' => $dsItem->product_item_id,
                        'bom_id' => $bom->id,
                        'qty_required' => $qtyToProduce,
                        'target_start_date' => now()->addDay()->toDateString(),
                        'target_end_date' => now()->addDays(max(1, $bom->standard_production_days ?? 7))->toDateString(),
                        'notes' => "Auto-created from DS {$ds->ds_reference} item #{$dsItem->id}. Stock: {$availableStock}, To produce: {$qtyToProduce}",
                    ], $systemUser);

                    Log::info("DS-Auto-PO: Created PO {$po->po_reference} for DSI #{$dsItem->id} in DS {$ds->ds_reference}");
                } catch (\Throwable $e) {
                    Log::error("DS-Auto-PO: Failed for DSI #{$dsItem->id}: {$e->getMessage()}");
                }
            }

            return;
        }

        // Fallback: single-item DS (legacy behavior)
        if ($ds->productionOrders()->count() > 0) {
            Log::info("DS-Auto-PO: Production orders already exist for DS {$ds->ds_reference}");

            return;
        }

        $availableStock = $this->getAvailableStock($ds->product_item_id);
        $requiredQty = (float) $ds->qty_ordered;

        if ($availableStock >= $requiredQty) {
            Log::info("DS-Auto-PO: Sufficient stock ({$availableStock}) for DS {$ds->ds_reference}, skipping PO creation");

            return;
        }

        $bom = BillOfMaterials::where('product_item_id', $ds->product_item_id)
            ->where('is_active', true)
            ->latest('version')
            ->first();

        if ($bom === null) {
            Log::warning("DS-Auto-PO: No active BOM found for product {$ds->product_item_id}, cannot auto-create PO");

            return;
        }

        $qtyToProduce = $requiredQty - $availableStock;

        try {
            $po = $this->createProductionOrderFromDeliverySchedule($ds, $bom, $qtyToProduce, $systemUser);
            Log::info("DS-Auto-PO: Created Production Order {$po->po_reference} for DS {$ds->ds_reference}");
        } catch (\Throwable $e) {
            Log::error("DS-Auto-PO: Failed to create PO for DS {$ds->ds_reference}: {$e->getMessage()}");
        }
    }

    /**
     * Get available stock for an item across all locations.
     */
    private function getAvailableStock(int $itemId): float
    {
        return (float) StockBalance::where('item_id', $itemId)
            ->sum('quantity_on_hand');
    }

    /**
     * Create a Production Order from a Delivery Schedule.
     */
    private function createProductionOrderFromDeliverySchedule(
        DeliverySchedule $ds,
        BillOfMaterials $bom,
        float $qtyToProduce,
        User $actor,
    ): ProductionOrder {
        // Calculate target dates based on delivery date
        $deliveryDate = $ds->target_delivery_date;
        $leadTimeDays = max(1, $bom->standard_production_days ?? 7);

        $targetStartDate = $deliveryDate->copy()->subDays($leadTimeDays);
        $targetEndDate = $deliveryDate->copy()->subDays(1);

        // Ensure start date is not in the past
        if ($targetStartDate->isPast()) {
            $targetStartDate = now()->addDay();
            $targetEndDate = $targetStartDate->copy()->addDays($leadTimeDays - 1);
        }

        // Delegate to ProductionOrderService::store() for consistent
        // po_reference generation, BOM snapshot, and cost estimation.
        return $this->poService->store([
            'delivery_schedule_id' => $ds->id,
            'source_type' => 'delivery_schedule',
            'source_id' => $ds->id,
            'product_item_id' => $ds->product_item_id,
            'bom_id' => $bom->id,
            'qty_required' => $qtyToProduce,
            'target_start_date' => $targetStartDate->toDateString(),
            'target_end_date' => $targetEndDate->toDateString(),
            'notes' => "Auto-created from Delivery Schedule {$ds->ds_reference}. Customer order: {$ds->qty_ordered}, Available stock: " . ($ds->qty_ordered - $qtyToProduce) . ", To produce: {$qtyToProduce}",
        ], $actor);
    }

    /**
     * Fulfill delivery schedule directly from stock without creating a Production Order.
     * This is used when sufficient stock is available.
     *
     * @throws DomainException if insufficient stock or schedule not in valid state
     */
    public function fulfillFromStock(DeliverySchedule $ds, int $userId): DeliverySchedule
    {
        // Validate schedule state
        if ($ds->status !== 'open' && $ds->status !== 'in_production') {
            throw new DomainException(
                "Cannot fulfill schedule in status: {$ds->status}. Must be 'open' or 'in_production'.",
                'INVALID_SCHEDULE_STATUS',
                422
            );
        }

        // Check if production orders exist
        if ($ds->productionOrders()->count() > 0) {
            throw new DomainException(
                'Cannot fulfill from stock: Production orders already exist for this schedule.',
                'PRODUCTION_ORDERS_EXIST',
                422
            );
        }

        // Check available stock
        $availableStock = $this->getAvailableStock($ds->product_item_id);
        $requiredQty = (float) $ds->qty_ordered;

        if ($availableStock < $requiredQty) {
            throw new DomainException(
                sprintf(
                    'Insufficient stock to fulfill order. Available: %.4f, Required: %.4f',
                    $availableStock,
                    $requiredQty
                ),
                'INSUFFICIENT_STOCK',
                422
            );
        }

        return DB::transaction(function () use ($ds, $userId, $requiredQty): DeliverySchedule {
            // Deduct stock via StockService (maintains audit trail in stock_ledger)
            $locationId = $this->getDefaultWarehouseLocation();
            $actor = User::findOrFail($userId);

            $this->stockService->issue(
                itemId: $ds->product_item_id,
                locationId: $locationId,
                quantity: $requiredQty,
                referenceType: DeliverySchedule::class,
                referenceId: $ds->id,
                actor: $actor,
                remarks: "Direct fulfillment for Delivery Schedule {$ds->ds_reference}",
            );

            // Update delivery schedule status to ready
            $ds->update([
                'status' => 'ready',
                'notes' => $ds->notes."\n[Fulfilled from stock] Direct fulfillment completed.",
            ]);

            Log::info("Delivery Schedule {$ds->ds_reference} fulfilled from stock. Qty: {$requiredQty}");

            return $ds->fresh(['customer', 'productItem', 'productionOrders']);
        });
    }

    // ── Workflow Methods (migrated from CombinedDeliveryScheduleService) ────

    /**
     * Dispatch a delivery schedule: creates DR, transitions to dispatched.
     */
    public function dispatchSchedule(
        DeliverySchedule $ds,
        int $userId,
        ?string $deliveryNotes = null
    ): DeliverySchedule {
        if (! in_array($ds->status, ['ready', 'partially_ready'], true)) {
            throw new DomainException(
                "Cannot dispatch schedule in status: {$ds->status}. Must be 'ready' or 'partially_ready'.",
                'SCHEDULE_NOT_READY',
                422
            );
        }

        return DB::transaction(function () use ($ds, $userId, $deliveryNotes): DeliverySchedule {
            $ds->update([
                'status' => 'dispatched',
                'dispatched_by_id' => $userId,
                'dispatched_at' => now(),
            ]);

            // Update child item statuses
            foreach ($ds->items as $item) {
                if ($item->status === 'ready') {
                    $item->update([
                        'status' => 'dispatched',
                        'notes' => ($item->notes ?? '')."\nDispatched: ".now()->toDateString().($deliveryNotes ? " - {$deliveryNotes}" : ''),
                    ]);
                }
            }

            // Create delivery receipt
            $dr = DeliveryReceipt::create([
                'customer_id' => $ds->customer_id,
                'delivery_schedule_id' => $ds->id,
                'direction' => 'outbound',
                'status' => 'draft',
                'remarks' => $deliveryNotes,
                'created_by_id' => $userId,
            ]);
            $dr->update(['status' => 'confirmed']);

            // GAP-P7: Create DR line items from DS items for warehouse pick/pack
            foreach ($ds->items as $dsItem) {
                if (in_array($dsItem->status, ['ready', 'dispatched'], true)) {
                    DeliveryReceiptItem::create([
                        'delivery_receipt_id' => $dr->id,
                        'item_master_id' => $dsItem->product_item_id,
                        'quantity_expected' => (float) $dsItem->qty_ordered,
                        'quantity_received' => (float) $dsItem->qty_ordered,
                        'unit_of_measure' => $dsItem->productItem?->unit_of_measure ?? 'pcs',
                        'remarks' => $dsItem->notes,
                    ]);
                }
            }

            // Store DR link on the schedule
            $ds->update(['delivery_receipt_id' => $dr->id]);

            // Sync ClientOrder to dispatched
            if ($ds->client_order_id) {
                $co = ClientOrder::find($ds->client_order_id);
                if ($co && $co->status === 'ready_for_delivery') {
                    $co->update(['status' => 'dispatched']);
                }
            }

            return $ds->fresh();
        });
    }

    /**
     * Mark schedule as physically delivered.
     */
    public function markScheduleDelivered(
        DeliverySchedule $ds,
        string $deliveryDate,
        int $userId
    ): DeliverySchedule {
        if ($ds->status !== 'dispatched') {
            throw new DomainException(
                'Cannot mark as delivered. Schedule must be dispatched first.',
                'SCHEDULE_NOT_DISPATCHED',
                422
            );
        }

        return DB::transaction(function () use ($ds, $deliveryDate): DeliverySchedule {
            $ds->update([
                'status' => 'delivered',
                'actual_delivery_date' => $deliveryDate,
            ]);

            foreach ($ds->items as $item) {
                $item->update(['status' => 'delivered']);
            }

            // Sync ClientOrder
            if ($ds->client_order_id) {
                $co = ClientOrder::find($ds->client_order_id);
                if ($co && in_array($co->status, ['approved', 'in_production', 'ready_for_delivery', 'dispatched'], true)) {
                    $co->update(['status' => 'delivered']);
                }
            }

            return $ds->fresh();
        });
    }

    /**
     * Client acknowledges receipt -- per-item condition reporting + auto-invoice.
     *
     * @param  array<array{item_id: int, received_qty: float, condition: string, notes?: string}>  $itemAcknowledgments
     */
    public function acknowledgeReceipt(
        DeliverySchedule $ds,
        array $itemAcknowledgments,
        ?string $generalNotes,
        int $userId
    ): DeliverySchedule {
        if ($ds->status !== 'delivered') {
            throw new DomainException(
                'Cannot acknowledge. Delivery must be marked as delivered first.',
                'SCHEDULE_NOT_DELIVERED',
                422
            );
        }

        $itemSummary = is_array($ds->item_status_summary) ? $ds->item_status_summary : [];
        $missingItemIds = collect($itemSummary)
            ->filter(fn ($item): bool => (bool) ($item['is_missing'] ?? false))
            ->map(fn ($item): int => (int) ($item['delivery_schedule_item_id'] ?? $item['delivery_schedule_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0);

        $requiredAcknowledgmentCount = $ds->items
            ->reject(fn (DeliveryScheduleItem $item): bool => $missingItemIds->contains($item->id))
            ->count();

        if (count($itemAcknowledgments) !== $requiredAcknowledgmentCount) {
            throw new DomainException(
                'All delivered items must be acknowledged.',
                'INCOMPLETE_ACKNOWLEDGMENT',
                422
            );
        }

        return DB::transaction(function () use ($ds, $itemAcknowledgments, $userId): DeliverySchedule {
            $hasIssues = false;
            $disputeItems = [];

            foreach ($itemAcknowledgments as $ack) {
                $item = DeliveryScheduleItem::find($ack['item_id']);
                if (! $item || $item->delivery_schedule_id !== $ds->id) {
                    continue;
                }

                // Store on parent DS client_acknowledgment JSON
                $existingAck = $ds->client_acknowledgment ?? [];
                $existingAck[] = [
                    'item_id' => $item->id,
                    'received_qty' => $ack['received_qty'],
                    'condition' => $ack['condition'],
                    'notes' => $ack['notes'] ?? null,
                    'acknowledged_at' => now()->toIso8601String(),
                    'acknowledged_by' => $userId,
                ];
                $ds->update(['client_acknowledgment' => $existingAck]);

                if ($ack['condition'] !== 'good') {
                    $hasIssues = true;
                    $disputeItems[] = [
                        'delivery_schedule_item_id' => $item->id,
                        'item_master_id' => $item->product_item_id,
                        'condition' => $ack['condition'],
                        'received_qty' => $ack['received_qty'],
                        'notes' => $ack['notes'] ?? null,
                    ];
                }
            }

            if ($hasIssues) {
                $ds->update([
                    'has_dispute' => true,
                    'dispute_summary' => $disputeItems,
                ]);

                DB::afterCommit(function () use ($ds): void {
                    User::permission('sales.order_review')
                        ->each(fn (User $u) => $u->notify(DeliveryDisputeNotification::fromModel($ds->combinedDeliverySchedule ?? $ds)));
                });
            }

            // Transition ClientOrder to fulfilled
            if ($ds->client_order_id) {
                $co = ClientOrder::find($ds->client_order_id);
                if ($co && in_array($co->status, ['delivered', 'dispatched', 'ready_for_delivery'], true)) {
                    $co->update(['status' => 'fulfilled']);

                    // GAP-P8: Auto-create draft Customer Invoice on successful acknowledgment
                    if (! $hasIssues) {
                        try {
                            $this->autoCreateCustomerInvoice($co, $ds, $userId);
                        } catch (\Throwable $e) {
                            // Non-fatal: invoice creation failure should not block fulfillment
                            Log::warning("[DS] Auto-invoice creation failed for CO #{$co->id}: {$e->getMessage()}");
                        }
                    }
                }
            }

            return $ds->fresh();
        });
    }

    /**
     * Notify client about missing/delayed items.
     */
    public function notifyMissingItems(
        DeliverySchedule $ds,
        array $missingItems,
        ?string $expectedDeliveryDate,
        ?string $message,
        int $userId
    ): void {
        $itemSummary = $ds->item_status_summary ?? [];

        foreach ($missingItems as $missingItem) {
            foreach ($itemSummary as &$item) {
                if (($item['delivery_schedule_item_id'] ?? null) === $missingItem['item_id']) {
                    $item['is_missing'] = true;
                    $item['missing_reason'] = $missingItem['reason'];
                    $item['expected_delivery'] = $expectedDeliveryDate;
                }
            }
        }

        $ds->update([
            'item_status_summary' => $itemSummary,
            'status' => 'partially_ready',
        ]);
    }

    /**
     * Get default warehouse location ID.
     */
    private function getDefaultWarehouseLocation(): int
    {
        // Try to find default/main warehouse
        $defaultLocation = DB::table('warehouse_locations')
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN name ILIKE '%main%' OR name ILIKE '%default%' THEN 0 ELSE 1 END")
            ->first();

        if ($defaultLocation) {
            return $defaultLocation->id;
        }

        // Fallback to first active location
        $firstLocation = DB::table('warehouse_locations')
            ->where('is_active', true)
            ->first();

        if (! $firstLocation) {
            throw new DomainException(
                'No warehouse location found. Please set up warehouse locations first.',
                'NO_WAREHOUSE_LOCATION',
                422
            );
        }

        return $firstLocation->id;
    }

    /**
     * GAP-P8: Auto-create a draft Customer Invoice from a fulfilled Client Order.
     *
     * Creates a draft invoice with line items from the client order.
     * The accounting team can then review and approve it.
     */
    private function autoCreateCustomerInvoice(ClientOrder $co, DeliverySchedule $ds, int $userId): void
    {
        // Guard: don't create duplicate invoices
        $existingInvoice = CustomerInvoice::where('client_order_id', $co->id)->exists();
        if ($existingInvoice) {
            Log::info("[DS] Auto-invoice skipped: invoice already exists for CO #{$co->id}");

            return;
        }

        // Find the current open fiscal period
        $currentPeriod = FiscalPeriod::where('status', 'open')
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->first();

        if ($currentPeriod === null) {
            Log::warning("[DS] Auto-invoice skipped: no open fiscal period for CO #{$co->id}");

            return;
        }

        $co->loadMissing('items.itemMaster', 'customer');

        $invoice = CustomerInvoice::create([
            'customer_id' => $co->customer_id,
            'client_order_id' => $co->id,
            'delivery_schedule_id' => $ds->id,
            'fiscal_period_id' => $currentPeriod->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays($co->customer->payment_terms_days ?? 30)->toDateString(),
            'status' => 'draft',
            'subtotal_centavos' => $co->total_amount_centavos,
            'tax_centavos' => 0,
            'total_centavos' => $co->total_amount_centavos,
            'balance_due_centavos' => $co->total_amount_centavos,
            'notes' => "Auto-generated from Client Order {$co->order_reference}, Delivery Schedule {$ds->ds_reference}",
            'created_by_id' => $userId,
        ]);

        Log::info("[DS] Auto-created draft Customer Invoice #{$invoice->id} for CO #{$co->id}");
    }
}
