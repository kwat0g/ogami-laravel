<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

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
        // Auto-calculate end date if not provided but BOM and start date are present
        if (empty($data['target_end_date']) && ! empty($data['target_start_date']) && ! empty($data['bom_id'])) {
            $data['target_end_date'] = $this->calculateEndDate($data['target_start_date'], $data['bom_id']);
        }

        // Snapshot BOM standard cost at PO creation time (real-world ERP
        // pattern: freeze the cost estimate so variance analysis works even
        // if BOM prices change later).
        $standardUnitCost = 0;
        $estimatedTotalCost = 0;

        if (! empty($data['bom_id'])) {
            $bom = BillOfMaterials::find($data['bom_id']);
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
            }
        }

        /** @var ProductionOrder $order */
        $order = ProductionOrder::create([
            'po_reference' => $this->generateReference(),
            'delivery_schedule_id' => $data['delivery_schedule_id'] ?? null,
            'product_item_id' => $data['product_item_id'],
            'bom_id' => $data['bom_id'],
            'qty_required' => $data['qty_required'],
            'standard_unit_cost_centavos' => $standardUnitCost,
            'estimated_total_cost_centavos' => $estimatedTotalCost,
            'target_start_date' => $data['target_start_date'],
            'target_end_date' => $data['target_end_date'],
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by_id' => $user->id,
        ]);

        return $order->load('productItem', 'bom', 'createdBy');
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
                    } catch (\Throwable) {
                        // Non-fatal: MRQ auto-creation failure should not block production release
                        Log::warning('PROD-002: Auto MRQ creation failed for order '.$order->id);
                    }
                }
            }

            return $order->refresh();
        });
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

            // Auto-post production cost variance to GL
            try {
                $costPostingService = app(ProductionCostPostingService::class);
                $actor2 = auth()->user() ?? User::where('email', config('ogami.system_user_email', 'admin@ogamierp.local'))->first();
                if ($actor2) {
                    $costPostingService->postCostVariance($order->fresh(), $actor2);
                }
            } catch (\Throwable $e) {
                // Non-fatal: cost posting failure should not block production completion
                Log::warning("PROD-COST: Auto cost variance posting failed for order #{$order->id}: {$e->getMessage()}");
            }

            // PROD-DEL-001: Notify Delivery domain AFTER the transaction commits so the
            // queued CreateDeliveryReceiptOnProductionComplete listener reads committed data.
            DB::afterCommit(fn () => ProductionOrderCompleted::dispatch($order->fresh()));

            return $order->refresh();
        });
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
     */
    public function hold(ProductionOrder $order, ?string $reason = null): ProductionOrder
    {
        if (! in_array($order->status, ['released', 'in_progress'], true)) {
            throw new DomainException('Only released or in-progress orders can be placed on hold.', 'PROD_ORDER_NOT_HOLDABLE', 422);
        }

        $order->update([
            'status' => 'on_hold',
            'hold_reason' => $reason,
        ]);

        return $order->refresh();
    }

    /**
     * Resume a held work order, returning it to in_progress.
     */
    public function resume(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== 'on_hold') {
            throw new DomainException('Only on-hold orders can be resumed.', 'PROD_ORDER_NOT_ON_HOLD', 422);
        }

        $order->update([
            'status' => 'in_progress',
            'hold_reason' => null,
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
