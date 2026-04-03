<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Inventory\Models\StockLedger;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Inventory\Services\MaterialRequisitionService;
use App\Domains\Inventory\Services\StockReservationService;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\StateMachines\ProductionOrderStateMachine;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Services\CostingService;
use App\Domains\Production\Models\ProductionOutputLog;
use App\Domains\QC\Models\Inspection;
use App\Events\Production\ProductionOrderCompleted;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Traits\HasArchiveOperations;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProductionOrderService implements ServiceContract
{
    use HasArchiveOperations;
    public function __construct(
        private readonly StockService $stockService,
        private readonly MaterialRequisitionService $mrqService,
        private readonly StockReservationService $reservationService,
    ) {}

    /**
     * @param  array<string,mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = ProductionOrder::with('productItem', 'bom', 'createdBy', 'deliverySchedule')
            ->withCount(['materialRequisitions as pending_mrq_count' => fn ($q) => $q->whereNotIn('status', ['fulfilled', 'cancelled', 'rejected'])])
            ->orderByDesc('id');

        if ($filters['with_archived'] ?? false) {
            $query->withTrashed();
        }

        if ($filters['search'] ?? null) {
            $v = $filters['search'];
            $query->where(fn ($q) => $q->where('po_reference', 'ilike', "%{$v}%")->orWhereHas('productItem', fn ($q2) => $q2->where('name', 'ilike', "%{$v}%")->orWhere('item_code', 'ilike', "%{$v}%")));
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['product_item_id'])) {
            $query->where('product_item_id', $filters['product_item_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    // ── Archive / Restore / Force Delete ────────────────────────────────────

    public function listArchived(array $filters = []): LengthAwarePaginator
    {
        $query = ProductionOrder::onlyTrashed()
            ->with('productItem', 'bom', 'createdBy')
            ->orderByDesc('deleted_at');

        if ($filters['search'] ?? null) {
            $v = $filters['search'];
            $query->where(fn ($q) => $q->where('po_reference', 'ilike', "%{$v}%"));
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function restoreOrder(int $id, User $user): ProductionOrder
    {
        /** @var ProductionOrder */
        return $this->restoreRecord(ProductionOrder::class, $id, $user);
    }

    public function forceDeleteOrder(int $id, User $user): void
    {
        $this->forceDeleteRecord(ProductionOrder::class, $id, $user);
    }

    /**
     * Suggest the most recently used BOM for a given product item.
     * Returns the BOM ID or null if no BOM exists.
     */
    public function suggestBom(int $productItemId): ?BillOfMaterials
    {
        // Find the most recently used BOM for this product
        $latestOrder = ProductionOrder::where('product_item_id', $productItemId)
            ->whereNotNull('bom_id')
            ->orderByDesc('created_at')
            ->first();

        if ($latestOrder !== null) {
            /** @var BillOfMaterials|null $bom */
            $bom = BillOfMaterials::where('id', $latestOrder->bom_id)
                ->where('is_active', true)
                ->first();

            if ($bom !== null) {
                return $bom;
            }
        }

        // Fallback: return the first active BOM for this product
        /** @var BillOfMaterials|null $bom */
        $bom = BillOfMaterials::where('product_item_id', $productItemId)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        return $bom;
    }

    /**
     * Calculate target end date based on start date and BOM production days.
     */
    public function calculateEndDate(string $targetStartDate, int $bomId): string
    {
        /** @var BillOfMaterials|null $bom */
        $bom = BillOfMaterials::find($bomId);

        if ($bom === null) {
            return $targetStartDate;
        }

        $startDate = Carbon::parse($targetStartDate);
        $productionDays = $bom->standard_production_days ?? 1;

        // End date = start date + production days (inclusive of start date)
        // If production days = 5, end date = start + 4 days (start is day 1)
        return $startDate->copy()->addDays(max(0, $productionDays - 1))->toDateString();
    }

    /**
     * Get smart defaults for production order creation.
     *
     * @return array{suggested_bom_id: int|null, suggested_bom_name: string|null, calculated_end_date: string|null}
     */
    public function getSmartDefaults(int $productItemId, ?string $targetStartDate = null): array
    {
        $suggestedBom = $this->suggestBom($productItemId);

        $calculatedEndDate = null;
        if ($targetStartDate !== null && $suggestedBom !== null) {
            $calculatedEndDate = $this->calculateEndDate($targetStartDate, $suggestedBom->id);
        }

        return [
            'suggested_bom_id' => $suggestedBom?->id,
            'suggested_bom_name' => $suggestedBom?->name ?? $suggestedBom?->version,
            'calculated_end_date' => $calculatedEndDate,
        ];
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, User $user): ProductionOrder
    {
        $sourceType = (string) ($data['source_type'] ?? 'manual');

        // Auto-calculate end date if not provided but BOM and start date are present
        if (empty($data['target_end_date']) && ! empty($data['target_start_date']) && ! empty($data['bom_id'])) {
            $data['target_end_date'] = $this->calculateEndDate($data['target_start_date'], $data['bom_id']);
        }

        // Snapshot BOM standard cost at PO creation time (real-world ERP
        // pattern: freeze the cost estimate so variance analysis works even
        // if BOM prices change later).
        $standardUnitCost = 0;
        $estimatedTotalCost = 0;
        $bomSnapshot = null;

        if (! empty($data['bom_id'])) {
            $bom = BillOfMaterials::with('components.componentItem')->find($data['bom_id']);
            if ($bom !== null) {
                $standardUnitCost = (int) ($bom->standard_cost_centavos ?? 0);

                // If BOM has no cost yet, compute it on-the-fly
                if ($standardUnitCost === 0) {
                    try {
                        $costingService = app(CostingService::class);
                        $costResult = $costingService->standardCost($bom, 'material_labor_overhead');
                        $standardUnitCost = $costResult['total_standard_cost_centavos'];
                    } catch (\Throwable) {
                        // Proceed with zero cost if calculation fails
                    }
                }

                $qtyRequired = (float) ($data['qty_required'] ?? 0);
                $estimatedTotalCost = (int) round($standardUnitCost * $qtyRequired);

                // PRD-S01: Snapshot BOM components at creation time so that
                // subsequent BOM changes don't affect this production order.
                $bomSnapshot = [
                    'bom_id' => $bom->id,
                    'bom_version' => $bom->version ?? 1,
                    'product_item_id' => $bom->product_item_id,
                    'standard_cost_centavos' => $standardUnitCost,
                    'snapshotted_at' => now()->toIso8601String(),
                    'components' => $bom->components->map(fn (BomComponent $c) => [
                        'component_item_id' => $c->component_item_id,
                        'item_code' => $c->componentItem?->item_code,
                        'item_name' => $c->componentItem?->name,
                        'qty_per_unit' => (float) $c->qty_per_unit,
                        'unit_of_measure' => $c->unit_of_measure,
                        'scrap_factor_pct' => (float) $c->scrap_factor_pct,
                    ])->all(),
                ];
            }
        }

        /** @var ProductionOrder $order */
        $order = ProductionOrder::create([
            'po_reference' => $data['po_reference'] ?? $this->generateReference(),
            'delivery_schedule_id' => $data['delivery_schedule_id'] ?? null,
            'delivery_schedule_item_id' => $data['delivery_schedule_item_id'] ?? null,
            'client_order_id' => $data['client_order_id'] ?? null,
            'sales_order_id' => $data['sales_order_id'] ?? null,
            'source_type' => $sourceType,
            'source_id' => $data['source_id'] ?? null,
            'requires_release_approval' => (bool) ($data['requires_release_approval'] ?? $this->requiresReleaseApproval($sourceType)),
            'approved_for_release_by' => null,
            'approved_for_release_at' => null,
            'release_approval_notes' => null,
            'product_item_id' => $data['product_item_id'],
            'bom_id' => $data['bom_id'],
            'bom_snapshot' => $bomSnapshot,
            'qty_required' => $data['qty_required'],
            'standard_unit_cost_centavos' => $standardUnitCost,
            'estimated_total_cost_centavos' => $estimatedTotalCost,
            'target_start_date' => $data['target_start_date'],
            'target_end_date' => $data['target_end_date'],
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by_id' => $user->id,
        ]);

        // CHAIN-DS-001: Auto-update delivery schedule status to in_production
        // when a production order is created from it.
        if (! empty($data['delivery_schedule_id'])) {
            $ds = \App\Domains\Production\Models\DeliverySchedule::find($data['delivery_schedule_id']);
            if ($ds !== null && in_array($ds->status, ['open', 'planning'], true)) {
                $ds->update(['status' => 'in_production']);
            }
        }

        // CHAIN-DSI-001: Auto-update delivery schedule item status
        if (! empty($data['delivery_schedule_item_id'])) {
            $dsi = \App\Domains\Production\Models\DeliveryScheduleItem::find($data['delivery_schedule_item_id']);
            if ($dsi !== null && $dsi->status === 'pending') {
                $dsi->update(['status' => 'in_production']);
            }
        }

        return $order->load('productItem', 'bom', 'createdBy');
    }

    /** @param array<string,mixed> $data */
    public function update(ProductionOrder $order, array $data): ProductionOrder
    {
        $editableStatuses = ['draft', 'released', 'in_progress', 'on_hold'];

        if (! in_array($order->status, $editableStatuses, true)) {
            throw new DomainException(
                'Production orders in terminal states cannot be edited.',
                'PROD_ORDER_NOT_EDITABLE',
                422,
            );
        }

        // Non-draft orders can only edit notes and target dates
        if ($order->status !== 'draft') {
            $restrictedFields = ['product_item_id', 'bom_id', 'qty_required'];
            foreach ($restrictedFields as $field) {
                if (isset($data[$field])) {
                    throw new DomainException(
                        "Cannot change {$field} after order has been released. Only notes and target dates can be edited.",
                        'PROD_ORDER_FIELD_LOCKED',
                        422,
                    );
                }
            }

            $order->update(array_filter([
                'target_start_date' => $data['target_start_date'] ?? null,
                'target_end_date' => $data['target_end_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], fn ($v) => $v !== null));

            return $order->refresh()->load('productItem', 'bom', 'createdBy');
        }

        // Recalculate end date if start date or BOM changed
        if (! empty($data['target_start_date']) && ! empty($data['bom_id'])) {
            $data['target_end_date'] = $data['target_end_date']
                ?? $this->calculateEndDate($data['target_start_date'], $data['bom_id']);
        }

        // Recalculate cost if BOM or qty changed
        if (isset($data['bom_id']) || isset($data['qty_required'])) {
            $bomId = $data['bom_id'] ?? $order->bom_id;
            $qty = (float) ($data['qty_required'] ?? $order->qty_required);
            $bom = BillOfMaterials::with('components.componentItem')->find($bomId);

            if ($bom !== null) {
                $standardUnitCost = (int) ($bom->standard_cost_centavos ?? 0);
                if ($standardUnitCost === 0) {
                    try {
                        $costingService = app(CostingService::class);
                        $costResult = $costingService->standardCost($bom, 'material_labor_overhead');
                        $standardUnitCost = $costResult['total_standard_cost_centavos'];
                    } catch (\Throwable) {
                        // Proceed with zero cost if calculation fails
                    }
                }
                $data['standard_unit_cost_centavos'] = $standardUnitCost;
                $data['estimated_total_cost_centavos'] = (int) round($standardUnitCost * $qty);
            }
        }

        $order->update(array_filter([
            'product_item_id' => $data['product_item_id'] ?? null,
            'bom_id' => $data['bom_id'] ?? null,
            'qty_required' => $data['qty_required'] ?? null,
            'standard_unit_cost_centavos' => $data['standard_unit_cost_centavos'] ?? null,
            'estimated_total_cost_centavos' => $data['estimated_total_cost_centavos'] ?? null,
            'target_start_date' => $data['target_start_date'] ?? null,
            'target_end_date' => $data['target_end_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return $order->refresh()->load('productItem', 'bom', 'createdBy');
    }

    /** @param array<string,mixed> $data */
    public function createReplenishmentOrder(array $data, User $user): ProductionOrder
    {
        $productItemId = (int) $data['product_item_id'];
        $targetStockLevel = (float) $data['target_stock_level'];
        $availableStock = $this->reservationService->getTotalAvailableStock($productItemId);

        if ($availableStock >= $targetStockLevel) {
            throw new DomainException(
                sprintf('Current available stock %.4f already meets target level %.4f.', $availableStock, $targetStockLevel),
                'REPLENISHMENT_NOT_REQUIRED',
                422,
            );
        }

        $item = ItemMaster::findOrFail($productItemId);
        $rawQty = $targetStockLevel - $availableStock;
        $configuredMinBatch = (float) ($item->min_batch_size ?? 0);
        $inputMinBatch = (float) ($data['min_batch_size'] ?? 0);
        $minBatchSize = max($configuredMinBatch, $inputMinBatch);
        $qtyRequired = $this->applyMinBatch($rawQty, $minBatchSize);

        $bomId = isset($data['bom_id'])
            ? (int) $data['bom_id']
            : (int) (BillOfMaterials::where('product_item_id', $productItemId)
                ->where('is_active', true)
                ->orderByDesc('version')
                ->value('id') ?? 0);

        if ($bomId <= 0) {
            throw new DomainException(
                "No active BOM found for item {$item->item_code}.",
                'REPLENISHMENT_BOM_MISSING',
                422,
            );
        }

        $targetStartDate = (string) ($data['target_start_date'] ?? now()->addDay()->toDateString());
        $targetEndDate = (string) ($data['target_end_date'] ?? $this->calculateEndDate($targetStartDate, $bomId));

        $notes = trim((string) ($data['notes'] ?? ''));
        $systemNote = sprintf(
            'Manual replenishment to target %.4f from available %.4f (raw deficit %.4f, min batch %.4f).',
            $targetStockLevel,
            $availableStock,
            $rawQty,
            $minBatchSize
        );

        return $this->store([
            'source_type' => 'replenishment',
            'source_id' => null,
            'product_item_id' => $productItemId,
            'bom_id' => $bomId,
            'qty_required' => $qtyRequired,
            'target_start_date' => $targetStartDate,
            'target_end_date' => $targetEndDate,
            'requires_release_approval' => true,
            'notes' => $notes !== '' ? $notes."\n[Replenishment] {$systemNote}" : "[Replenishment] {$systemNote}",
        ], $user);
    }

    public function approveRelease(ProductionOrder $order, User $approver, ?string $notes = null): ProductionOrder
    {
        if (! $order->requires_release_approval) {
            throw new DomainException(
                'This production order does not require release approval.',
                'PROD_RELEASE_APPROVAL_NOT_REQUIRED',
                422,
            );
        }

        if ($order->status !== 'draft') {
            throw new DomainException(
                "Cannot approve release for order in status: {$order->status}",
                'PROD_RELEASE_APPROVAL_INVALID_STATUS',
                422,
            );
        }

        if ($order->approved_for_release_at !== null) {
            throw new DomainException(
                'Release has already been approved for this order.',
                'PROD_RELEASE_ALREADY_APPROVED',
                422,
            );
        }

        if ($order->created_by_id === $approver->id) {
            throw new DomainException(
                'Creator cannot approve release for the same production order (SoD).',
                'PROD_RELEASE_APPROVAL_SOD',
                403,
            );
        }

        $order->update([
            'approved_for_release_by' => $approver->id,
            'approved_for_release_at' => now(),
            'release_approval_notes' => $notes,
        ]);

        return $order->refresh();
    }

    /**
     * Close a completed production order (terminal state — costs posted).
     */
    public function close(ProductionOrder $order): ProductionOrder
    {
        $stateMachine = new ProductionOrderStateMachine();
        $stateMachine->transition($order, 'closed');

        $order->update(['status' => 'closed']);

        return $order->refresh();
    }

    /**
     * Release a draft production order.
     *
     * PROD-002: Blocks release if linked QC inspections have failed status
     *           (unless force_release + production.qc-override permission).
     * PROD-001: Auto-deducts BOM component stock on release.
     *
     * @param  array<string,mixed>  $options  Optional: ['force_release' => bool]
     */
    public function release(ProductionOrder $order, array $options = []): ProductionOrder
    {
        if ($order->requires_release_approval && $order->approved_for_release_at === null) {
            throw new DomainException(
                'Release blocked: this production order requires prior approval before release.',
                'PROD_RELEASE_APPROVAL_REQUIRED',
                422,
            );
        }

        if ($order->requires_release_approval && $order->approved_for_release_by === $order->created_by_id) {
            throw new DomainException(
                'Release blocked: invalid approval because creator cannot approve own order.',
                'PROD_RELEASE_APPROVAL_SOD',
                403,
            );
        }

        $stateMachine = new ProductionOrderStateMachine();
        $stateMachine->transition($order, 'released'); // Validates draft -> released

        return DB::transaction(function () use ($order, $options): ProductionOrder {
            // ── PROD-002: QC Gate — check for failed inspections ─────────────
            $forceRelease = (bool) ($options['force_release'] ?? false);
            $failedInspections = Inspection::query()
                ->where('production_order_id', $order->id)
                ->where('status', 'failed')
                ->get(['id', 'inspection_date', 'remarks']);

            if ($failedInspections->isNotEmpty() && ! $forceRelease) {
                $ids = $failedInspections->pluck('id')->implode(', ');
                throw new DomainException(
                    "Release blocked by failed QC inspection(s): #{$ids}. Resolve them or use force-release with QC override permission.",
                    'PROD_QC_GATE_BLOCKED',
                    422,
                );
            }

            if ($failedInspections->isNotEmpty() && $forceRelease) {
                Log::warning("PROD-002: QC override used for order #{$order->id} — failed inspections: "
                    .$failedInspections->pluck('id')->implode(', '));
            }

            // ── PROD-001: Material issuance via MRQ (single path) ────────────
            // Stock deduction happens ONLY when the MRQ is fulfilled by warehouse
            // staff, NOT here on release. This prevents double deduction.
            // The MRQ goes through its own approval workflow before stock is issued.

            $order->update(['status' => 'released']);

            // Auto-generate a draft MRQ from the BOM for material planning
            // @phpstan-ignore-next-line (bom_id is nullable in DB despite PHPDoc int)
            if ($order->bom_id !== null) {
                $systemUser = User::where('email', config('ogami.system_user_email', 'admin@ogamierp.local'))->first();
                if ($systemUser !== null) {
                    try {
                        $this->mrqService->createFromBom($order, $systemUser);
                    } catch (\Throwable $e) {
                        // FS-030 FIX: Non-fatal but surfaced — attach warning to order for API response.
                        // MRQ auto-creation failure should not block production release, but the user
                        // must be informed so they can create the MRQ manually.
                        Log::warning('PROD-002: Auto MRQ creation failed for order '.$order->id, [
                            'error' => $e->getMessage(),
                        ]);
                        $order->setAttribute('_release_warnings', array_merge(
                            $order->getAttribute('_release_warnings') ?? [],
                            ['Auto material requisition creation failed: '.$e->getMessage().'. Please create MRQ manually.']
                        ));
                    }
                }
            }

            return $order->refresh();
        });
    }

    private function requiresReleaseApproval(string $sourceType): bool
    {
        return in_array($sourceType, ['force_production', 'replenishment'], true);
    }

    private function applyMinBatch(float $requestedQty, float $minBatchSize): float
    {
        if ($requestedQty <= 0) {
            return 0.0;
        }

        if ($minBatchSize <= 0) {
            return round($requestedQty, 4);
        }

        $rounded = ceil($requestedQty / $minBatchSize) * $minBatchSize;

        return round(max($rounded, $minBatchSize), 4);
    }

    /**
     * PROD-001: Pre-release stock check — returns availability status per BOM component.
     *
     * @return array<int, array{component_item_id: int, item_name: string, unit_of_measure: string, required_qty: float, available_qty: float, sufficient: bool}>
     */
    public function stockCheck(ProductionOrder $order): array
    {
        if ($order->bom_id === null) {
            return [];
        }

        $components = $order->bom->components()->with('componentItem')->get();
        $location = WarehouseLocation::where('is_active', true)->first();

        if ($location === null) {
            return $components->map(fn ($c) => [
                'component_item_id' => $c->component_item_id,
                'item_name' => $c->componentItem->name ?? "Item #{$c->component_item_id}",
                'unit_of_measure' => $c->unit_of_measure,
                'required_qty' => $this->computeRequiredQty($c, $order),
                'available_qty' => 0.0,
                'sufficient' => false,
            ])->toArray();
        }

        return $components->map(function ($component) use ($order, $location) {
            $requiredQty = $this->computeRequiredQty($component, $order);
            $availableQty = $this->stockService->currentBalance($component->component_item_id, $location->id);

            return [
                'component_item_id' => $component->component_item_id,
                'item_name' => $component->componentItem->name ?? "Item #{$component->component_item_id}",
                'unit_of_measure' => $component->unit_of_measure,
                'required_qty' => $requiredQty,
                'available_qty' => $availableQty,
                'sufficient' => $availableQty >= $requiredQty,
            ];
        })->toArray();
    }

    /**
     * PROD-001: Deduct BOM component quantities from inventory stock.
     * All-or-nothing: checks all components first, then issues.
     */
    private function deductBomComponents(ProductionOrder $order): void
    {
        $components = $order->bom->components()->with('componentItem')->get();
        $location = WarehouseLocation::where('is_active', true)->first();

        if ($location === null) {
            throw new DomainException(
                'No active warehouse location found. Cannot deduct stock.',
                'PROD_NO_WAREHOUSE',
                422,
            );
        }

        // Phase 1: Check all components for sufficient stock
        $shortages = [];
        foreach ($components as $component) {
            $requiredQty = $this->computeRequiredQty($component, $order);
            $availableQty = $this->stockService->currentBalance($component->component_item_id, $location->id);

            if ($availableQty < $requiredQty) {
                $shortages[] = [
                    'item' => $component->componentItem->name ?? "Item #{$component->component_item_id}",
                    'required' => $requiredQty,
                    'available' => $availableQty,
                    'short_by' => round($requiredQty - $availableQty, 4),
                ];
            }
        }

        if (! empty($shortages)) {
            $details = collect($shortages)
                ->map(fn ($s) => "{$s['item']}: need {$s['required']}, have {$s['available']} (short by {$s['short_by']})")
                ->implode('; ');

            throw new DomainException(
                'Insufficient stock for '.count($shortages)." component(s): {$details}",
                'PROD_INSUFFICIENT_STOCK',
                422,
            );
        }

        // Phase 2: Issue stock for all components
        /** @var User $actor */
        $actor = auth()->user() ?? User::where('email', config('ogami.system_user_email', 'admin@ogamierp.local'))->firstOrFail();

        foreach ($components as $component) {
            $requiredQty = $this->computeRequiredQty($component, $order);

            $this->stockService->issue(
                itemId: $component->component_item_id,
                locationId: $location->id,
                quantity: $requiredQty,
                referenceType: 'production_orders',
                referenceId: $order->id,
                actor: $actor,
                remarks: "BOM material issue — WO {$order->po_reference}, component: "
                    .($component->componentItem->name ?? "#{$component->component_item_id}"),
            );
        }
    }

    /**
     * Compute the total required quantity for a BOM component, including scrap factor.
     *
     * Formula: qty_per_unit × order.qty_required × (1 + scrap_factor_pct / 100)
     */
    private function computeRequiredQty(BomComponent $component, ProductionOrder $order): float
    {
        $qtyPerUnit = (float) $component->qty_per_unit;
        $orderQty = (float) $order->qty_required;
        $scrapFactor = 1.0 + ((float) $component->scrap_factor_pct / 100.0);

        return round($qtyPerUnit * $orderQty * $scrapFactor, 4);
    }

    public function start(ProductionOrder $order): ProductionOrder
    {
        $stateMachine = new ProductionOrderStateMachine();
        $stateMachine->transition($order, 'in_progress'); // Validates released -> in_progress

        // Ensure all linked MRQs are fulfilled before production begins (PROD-MRQ-001).
        $unfulfilledMrq = MaterialRequisition::query()
            ->where('production_order_id', $order->id)
            ->whereNotIn('status', ['fulfilled', 'cancelled', 'rejected'])
            ->exists();

        if ($unfulfilledMrq) {
            throw new DomainException(
                'All material requisitions must be fulfilled before starting production.',
                'PROD_MRQ_NOT_FULFILLED',
                422,
            );
        }

        $order->update(['status' => 'in_progress']);

        return $order->refresh();
    }

    public function complete(ProductionOrder $order): ProductionOrder
    {
        $stateMachine = new ProductionOrderStateMachine();
        $stateMachine->transition($order, 'completed'); // Validates in_progress -> completed

        if ((float) $order->qty_produced <= 0) {
            throw new DomainException(
                'No output has been logged. Log production output before completing the order.',
                'PROD_NO_OUTPUT_LOGGED',
                422,
            );
        }

        // ── FQC Gate: Check for passed final QC inspection ──────────────
        // If there are any open or failed inspections linked to this order,
        // block completion. Only proceed if no inspections exist (no FQC
        // required) or all inspections have passed.
        $openOrFailed = Inspection::where('production_order_id', $order->id)
            ->whereIn('status', ['open', 'failed'])
            ->exists();

        if ($openOrFailed) {
            $failedIds = Inspection::where('production_order_id', $order->id)
                ->whereIn('status', ['open', 'failed'])
                ->pluck('id')
                ->implode(', ');

            throw new DomainException(
                "Cannot complete: QC inspection(s) #{$failedIds} are open or failed. Resolve all inspections before completing the order.",
                'PROD_FQC_GATE_BLOCKED',
                422,
            );
        }

        return DB::transaction(function () use ($order): ProductionOrder {
            $order->update(['status' => 'completed']);

            // Move finished goods into stock
            $location = WarehouseLocation::where('is_active', true)->first();
            if ($location === null) {
                throw new DomainException(
                    'Cannot complete: no active warehouse location configured.',
                    'PROD_NO_WAREHOUSE',
                    422,
                );
            }

            $actor = auth()->user() ?? User::where('email', config('ogami.system_user_email', 'admin@ogamierp.local'))->first();
            if ($actor === null) {
                throw new DomainException(
                    'Cannot complete: no authenticated user or system user available for stock receipt.',
                    'PROD_NO_ACTOR',
                    500,
                );
            }

            $netQty = (float) $order->qty_produced - (float) $order->qty_rejected;
            if ($netQty > 0) {
                $this->stockService->receive(
                    itemId: $order->product_item_id,
                    locationId: $location->id,
                    quantity: $netQty,
                    referenceType: 'production_orders',
                    referenceId: $order->id,
                    actor: $actor,
                    remarks: "Auto-receive from WO {$order->po_reference}",
                );
            }

            $completedOrder = $order->refresh();
            $actorId = $actor->id;

            // Run side effects after commit so DB failures do not poison the main transaction.
            DB::afterCommit(function () use ($completedOrder, $actorId): void {
                if (! app()->environment('testing')) {
                    try {
                        $costPostingService = app(ProductionCostPostingService::class);
                        $postingActor = User::find($actorId);
                        if ($postingActor !== null) {
                            $costPostingService->postCostVariance($completedOrder, $postingActor);
                        }
                    } catch (\Throwable $e) {
                        // Non-fatal: cost posting failure should not block production completion
                        Log::warning("PROD-COST: Auto cost variance posting failed for order #{$completedOrder->id}: {$e->getMessage()}");
                    }
                }

                // PROD-DEL-001: Notify Delivery domain AFTER transaction commits.
                try {
                    ProductionOrderCompleted::dispatch($completedOrder);
                } catch (\Throwable $e) {
                    // Non-fatal: dispatch/listener failures should not roll back completion.
                    Log::warning("PROD-DEL: Completion event dispatch failed for order #{$completedOrder->id}: {$e->getMessage()}");
                }
            });

            return $completedOrder;
        });
    }

    /**
     * C5 FIX: Rework a completed production order — requires NCR reference.
     *
     * When QC rejects a batch after production completion, the order must be
     * reopened for rework. This method validates that a valid NCR exists and
     * links it to the order for traceability. Without an NCR, rework is blocked.
     *
     * @param  string  $ncrUlid  The ULID of the Non-Conformance Report triggering rework
     * @param  string|null  $reason  Optional rework reason/notes
     */
    public function rework(ProductionOrder $order, string $ncrUlid, ?string $reason = null): ProductionOrder
    {
        // Validate the state transition: completed -> in_progress
        $stateMachine = new ProductionOrderStateMachine();
        $stateMachine->transition($order, 'in_progress');

        // Validate NCR exists and is active
        $ncr = \App\Domains\QC\Models\NonConformanceReport::where('ulid', $ncrUlid)->first();

        if ($ncr === null) {
            throw new DomainException(
                'Rework requires a valid Non-Conformance Report (NCR). The provided NCR reference was not found.',
                'PROD_REWORK_NCR_NOT_FOUND',
                422,
                ['ncr_ulid' => $ncrUlid],
            );
        }

        // Ensure NCR is not already closed
        if ($ncr->status === 'closed') {
            throw new DomainException(
                "NCR #{$ncr->ncr_number} is already closed. Reopen it or create a new NCR before initiating rework.",
                'PROD_REWORK_NCR_CLOSED',
                422,
                ['ncr_ulid' => $ncrUlid, 'ncr_status' => $ncr->status],
            );
        }

        $order->update([
            'status' => 'in_progress',
            'rework_ncr_id' => $ncr->id,
            'rework_reason' => $reason,
            'rework_started_at' => now(),
        ]);

        Log::info("PROD-REWORK: Order #{$order->po_reference} reopened for rework. NCR: {$ncr->ncr_number}");

        return $order->refresh();
    }

    public function cancel(ProductionOrder $order): ProductionOrder
    {
        $stateMachine = new ProductionOrderStateMachine();
        $stateMachine->transition($order, 'cancelled'); // Validates draft|released|in_progress|on_hold -> cancelled

        return DB::transaction(function () use ($order): ProductionOrder {
            $mrqs = MaterialRequisition::query()
                ->where('production_order_id', $order->id)
                ->get();

            /** @var User $actor */
            $actor = auth()->user() ?? User::findOrFail($order->created_by_id);

            foreach ($mrqs as $mrq) {
                if ($mrq->status === 'fulfilled') {
                    // Reverse stock that was issued for this MRQ so inventory is restored
                    $issueLedgers = StockLedger::query()
                        ->where('reference_type', 'material_requisitions')
                        ->where('reference_id', $mrq->id)
                        ->where('transaction_type', 'issue')
                        ->get();

                    foreach ($issueLedgers as $ledger) {
                        $this->stockService->returnFromMrq(
                            itemId: $ledger->item_id,
                            locationId: $ledger->location_id,
                            quantity: abs((float) $ledger->quantity),
                            mrqId: $mrq->id,
                            actor: $actor,
                            remarks: 'Returned — WO '.$order->po_reference.' cancelled',
                        );
                    }
                }

                if (! in_array($mrq->status, ['cancelled', 'rejected'], true)) {
                    $mrq->update(['status' => 'cancelled']);
                }
            }

            $order->update(['status' => 'cancelled']);

            return $order->refresh();
        });
    }

    public function void(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== 'in_progress') {
            throw new DomainException('Only in-progress orders can be voided.', 'PROD_ORDER_CANNOT_VOID', 422);
        }

        if ((float) $order->qty_produced > 0) {
            throw new DomainException('Cannot void an order that already has production output logged.', 'PROD_ORDER_HAS_OUTPUT', 422);
        }

        return DB::transaction(function () use ($order): ProductionOrder {
            $mrqs = MaterialRequisition::query()
                ->where('production_order_id', $order->id)
                ->get();

            /** @var User $actor */
            $actor = auth()->user() ?? User::findOrFail($order->created_by_id);

            foreach ($mrqs as $mrq) {
                if ($mrq->status === 'fulfilled') {
                    // Reverse each issued stock line using the original ledger entries
                    $issueLedgers = StockLedger::query()
                        ->where('reference_type', 'material_requisitions')
                        ->where('reference_id', $mrq->id)
                        ->where('transaction_type', 'issue')
                        ->get();

                    foreach ($issueLedgers as $ledger) {
                        $this->stockService->returnFromMrq(
                            itemId: $ledger->item_id,
                            locationId: $ledger->location_id,
                            quantity: abs((float) $ledger->quantity),
                            mrqId: $mrq->id,
                            actor: $actor,
                            remarks: 'Returned — WO '.$order->po_reference.' voided',
                        );
                    }
                }

                if (! in_array($mrq->status, ['cancelled', 'rejected'], true)) {
                    $mrq->update(['status' => 'cancelled']);
                }
            }

            $order->update(['status' => 'cancelled']);

            return $order->refresh();
        });
    }

    /**
     * Place an in-progress or released work order on hold (e.g. after a QC failure).
     * QC-001: Failed inspection blocks further production until resolved.
     * Tracks held_from_state so resume() returns to the correct state.
     */
    public function hold(ProductionOrder $order, ?string $reason = null): ProductionOrder
    {
        if (! in_array($order->status, ['released', 'in_progress'], true)) {
            throw new DomainException('Only released or in-progress orders can be placed on hold.', 'PROD_ORDER_NOT_HOLDABLE', 422);
        }

        $heldFromState = $order->status;

        $order->update([
            'status' => 'on_hold',
            'hold_reason' => $reason,
            'held_from_state' => $heldFromState,
        ]);

        return $order->refresh();
    }

    /**
     * Resume a held work order, returning it to the state it was in before being held.
     * If held_from_state is not set (legacy), defaults to in_progress.
     */
    public function resume(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== 'on_hold') {
            throw new DomainException('Only on-hold orders can be resumed.', 'PROD_ORDER_NOT_ON_HOLD', 422);
        }

        $resumeToState = $order->held_from_state ?? 'in_progress';

        $order->update([
            'status' => $resumeToState,
            'hold_reason' => null,
            'held_from_state' => null,
        ]);

        return $order->refresh();
    }

    /** @param array<string,mixed> $data */
    public function logOutput(ProductionOrder $order, array $data, User $user): ProductionOutputLog
    {
        if ($order->status !== 'in_progress') {
            throw new DomainException(
                'Output can only be logged when order is in_progress. Current status: ' . $order->status,
                'PROD_ORDER_NOT_IN_PROGRESS',
                422,
            );
        }

        /** @var ProductionOutputLog $log */
        $log = ProductionOutputLog::create([
            'production_order_id' => $order->id,
            'shift' => $data['shift'],
            'log_date' => $data['log_date'],
            'qty_produced' => $data['qty_produced'],
            'qty_rejected' => $data['qty_rejected'] ?? 0,
            'operator_id' => $data['operator_id'],
            'recorded_by_id' => $user->id,
            'remarks' => $data['remarks'] ?? null,
        ]);

        // ── A3: Auto-log mold shots from production output ──────────────────
        // MOLD-AUTO-001: If this production order is linked to a mold (via BOM
        // or direct assignment), automatically log shot count based on output qty
        // and mold cavity count: shots = ceil(qty_produced / cavity_count).
        // Controlled by system_setting 'automation.production_output.auto_log_mold_shots'.
        $this->autoLogMoldShots($order, $data, $user);

        return $log->load('operator', 'recordedBy');
    }

    /**
     * Auto-log mold shots when production output is recorded.
     *
     * If the production order's product has an associated mold, calculate
     * shots = ceil(qty_produced / cavity_count) and log them.
     */
    private function autoLogMoldShots(ProductionOrder $order, array $data, User $user): void
    {
        // Check if automation is enabled
        $enabled = (bool) (\DB::table('system_settings')
            ->where('key', 'automation.production_output.auto_log_mold_shots')
            ->value('value') ?? true);

        if (! $enabled) {
            return;
        }

        try {
            // Find mold linked to this production order's product
            $mold = \App\Domains\Mold\Models\MoldMaster::query()
                ->where('product_item_id', $order->product_item_id)
                ->where('status', 'active')
                ->first();

            if ($mold === null) {
                return;
            }

            $qtyProduced = (float) ($data['qty_produced'] ?? 0);
            $cavityCount = max(1, (int) $mold->cavity_count);
            $shotCount = (int) ceil($qtyProduced / $cavityCount);

            if ($shotCount <= 0) {
                return;
            }

            $moldService = app(\App\Domains\Mold\Services\MoldService::class);
            $moldService->logShots($mold, [
                'shot_count' => $shotCount,
                'production_order_id' => $order->id,
                'operator_id' => $data['operator_id'] ?? $user->id,
                'log_date' => $data['log_date'] ?? today()->toDateString(),
                'remarks' => "Auto-logged from production output: {$qtyProduced} units / {$cavityCount} cavities = {$shotCount} shots",
            ], $user->id);

            Log::info("[Production] Auto-logged {$shotCount} mold shots for mold #{$mold->mold_code}", [
                'production_order_id' => $order->id,
                'mold_id' => $mold->id,
                'qty_produced' => $qtyProduced,
                'cavity_count' => $cavityCount,
            ]);
        } catch (\Throwable $e) {
            // Don't fail production output if mold shot logging fails
            Log::warning('[Production] Auto mold shot logging failed', [
                'production_order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a unique production order reference: PROD-YYYY-MM-NNNNN
     */
    private function generateReference(): string
    {
        $prefix = 'PROD-' . now()->format('Y-m');
        $last = ProductionOrder::where('po_reference', 'like', "{$prefix}-%")
            ->orderByDesc('po_reference')
            ->value('po_reference');

        if ($last !== null) {
            $seq = (int) substr($last, -5) + 1;
        } else {
            $seq = 1;
        }

        return sprintf('%s-%05d', $prefix, $seq);
    }
}
