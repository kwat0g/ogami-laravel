<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\DeliverySchedule;
use App\Domains\Production\Models\ProductionOrder;
use App\Models\User;
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
     * Creates PO if:
     * 1. No existing PO linked to this DS
     * 2. Stock is insufficient to fulfill the order
     * 3. BOM exists for the product
     */
    private function maybeAutoCreateProductionOrder(DeliverySchedule $ds): void
    {
        // Skip if production orders already exist for this DS
        if ($ds->productionOrders()->count() > 0) {
            Log::info("DS-Auto-PO: Production orders already exist for DS {$ds->ds_reference}");

            return;
        }

        // Get available stock for the product
        $availableStock = $this->getAvailableStock($ds->product_item_id);
        $requiredQty = (float) $ds->qty_ordered;

        // If sufficient stock, no need to produce
        if ($availableStock >= $requiredQty) {
            Log::info("DS-Auto-PO: Sufficient stock ({$availableStock}) for DS {$ds->ds_reference}, skipping PO creation");

            return;
        }

        // Find active BOM for the product
        $bom = BillOfMaterials::where('product_item_id', $ds->product_item_id)
            ->where('is_active', true)
            ->latest('version')
            ->first();

        if ($bom === null) {
            Log::warning("DS-Auto-PO: No active BOM found for product {$ds->product_item_id}, cannot auto-create PO");

            return;
        }

        // Calculate quantity to produce
        $qtyToProduce = $requiredQty - $availableStock;

        // Get system user for auto-creation
        $systemUser = User::where('email', 'admin@ogamierp.local')->first();
        if ($systemUser === null) {
            Log::error('DS-Auto-PO: System user not found, cannot auto-create PO');

            return;
        }

        try {
            $po = $this->createProductionOrderFromDeliverySchedule(
                $ds,
                $bom,
                $qtyToProduce,
                $systemUser
            );

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
        return DB::transaction(function () use ($ds, $bom, $qtyToProduce, $actor) {
            // Calculate target dates based on delivery date
            $deliveryDate = $ds->target_delivery_date;
            $leadTimeDays = 7; // Default lead time, could be calculated from BOM complexity

            $targetStartDate = $deliveryDate->copy()->subDays($leadTimeDays);
            $targetEndDate = $deliveryDate->copy()->subDays(1);

            // Ensure start date is not in the past
            if ($targetStartDate->isPast()) {
                $targetStartDate = now()->addDay();
                $targetEndDate = $targetStartDate->copy()->addDays($leadTimeDays - 1);
            }

            /** @var ProductionOrder $po */
            $po = ProductionOrder::create([
                'delivery_schedule_id' => $ds->id,
                'product_item_id' => $ds->product_item_id,
                'bom_id' => $bom->id,
                'qty_required' => $qtyToProduce,
                'target_start_date' => $targetStartDate,
                'target_end_date' => $targetEndDate,
                'status' => 'draft',
                'notes' => "Auto-created from Delivery Schedule {$ds->ds_reference}. Customer order: {$ds->qty_ordered}, Available stock: ".($ds->qty_ordered - $qtyToProduce).", To produce: {$qtyToProduce}",
                'created_by_id' => $actor->id,
            ]);

            // Reference is auto-generated by database trigger if not provided
            // Format: WO-YYYY-MM-NNNNN

            return $po->refresh();
        });
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
}
