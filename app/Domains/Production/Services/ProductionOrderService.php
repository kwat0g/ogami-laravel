<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Inventory\Models\StockLedger;
use App\Domains\Inventory\Services\MaterialRequisitionService;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOutputLog;
use App\Domains\QC\Models\Inspection;
use App\Events\Production\ProductionOrderCompleted;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProductionOrderService implements ServiceContract
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly MaterialRequisitionService $mrqService,
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

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['product_item_id'])) {
            $query->where('product_item_id', $filters['product_item_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, User $user): ProductionOrder
    {
        /** @var ProductionOrder $order */
        $order = ProductionOrder::create([
            'delivery_schedule_id' => $data['delivery_schedule_id'] ?? null,
            'product_item_id' => $data['product_item_id'],
            'bom_id' => $data['bom_id'],
            'qty_required' => $data['qty_required'],
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
        if ($order->status !== 'draft') {
            throw new DomainException('Only draft orders can be released.', 'PROD_ORDER_NOT_DRAFT', 422);
        }

        return DB::transaction(function () use ($order, $options): ProductionOrder {
            // ── PROD-002: QC Gate — check for failed inspections ─────────────
            $forceRelease = (bool) ($options['force_release'] ?? false);
            $failedInspections = Inspection::query()
                ->where('production_order_id', $order->id)
                ->where('status', 'failed')
                ->get(['id', 'inspection_date', 'remarks']);

            if ($failedInspections->isNotEmpty() && !$forceRelease) {
                $ids = $failedInspections->pluck('id')->implode(', ');
                throw new DomainException(
                    "Release blocked by failed QC inspection(s): #{$ids}. Resolve them or use force-release with QC override permission.",
                    'PROD_QC_GATE_BLOCKED',
                    422,
                );
            }

            if ($failedInspections->isNotEmpty() && $forceRelease) {
                Log::warning("PROD-002: QC override used for order #{$order->id} — failed inspections: "
                    . $failedInspections->pluck('id')->implode(', '));
            }

            // ── PROD-001: Deduct BOM component stock from inventory ─────────
            if ($order->bom_id !== null) {
                $this->deductBomComponents($order);
            }

            $order->update(['status' => 'released']);

            // Auto-generate a draft MRQ from the BOM for material planning
            // @phpstan-ignore-next-line (bom_id is nullable in DB despite PHPDoc int)
            if ($order->bom_id !== null) {
                $systemUser = User::where('email', 'admin@ogamierp.local')->first();
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
                'item_name'         => $c->componentItem->name ?? "Item #{$c->component_item_id}",
                'unit_of_measure'   => $c->unit_of_measure,
                'required_qty'      => $this->computeRequiredQty($c, $order),
                'available_qty'     => 0.0,
                'sufficient'        => false,
            ])->toArray();
        }

        return $components->map(function ($component) use ($order, $location) {
            $requiredQty  = $this->computeRequiredQty($component, $order);
            $availableQty = $this->stockService->currentBalance($component->component_item_id, $location->id);

            return [
                'component_item_id' => $component->component_item_id,
                'item_name'         => $component->componentItem->name ?? "Item #{$component->component_item_id}",
                'unit_of_measure'   => $component->unit_of_measure,
                'required_qty'      => $requiredQty,
                'available_qty'     => $availableQty,
                'sufficient'        => $availableQty >= $requiredQty,
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
        $location   = WarehouseLocation::where('is_active', true)->first();

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
            $requiredQty  = $this->computeRequiredQty($component, $order);
            $availableQty = $this->stockService->currentBalance($component->component_item_id, $location->id);

            if ($availableQty < $requiredQty) {
                $shortages[] = [
                    'item'      => $component->componentItem->name ?? "Item #{$component->component_item_id}",
                    'required'  => $requiredQty,
                    'available' => $availableQty,
                    'short_by'  => round($requiredQty - $availableQty, 4),
                ];
            }
        }

        if (!empty($shortages)) {
            $details = collect($shortages)
                ->map(fn ($s) => "{$s['item']}: need {$s['required']}, have {$s['available']} (short by {$s['short_by']})")
                ->implode('; ');

            throw new DomainException(
                "Insufficient stock for " . count($shortages) . " component(s): {$details}",
                'PROD_INSUFFICIENT_STOCK',
                422,
            );
        }

        // Phase 2: Issue stock for all components
        /** @var \App\Models\User $actor */
        $actor = auth()->user() ?? User::where('email', 'admin@ogamierp.local')->firstOrFail();

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
                    . ($component->componentItem->name ?? "#{$component->component_item_id}"),
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
        $qtyPerUnit   = (float) $component->qty_per_unit;
        $orderQty     = (float) $order->qty_required;
        $scrapFactor  = 1.0 + ((float) $component->scrap_factor_pct / 100.0);

        return round($qtyPerUnit * $orderQty * $scrapFactor, 4);
    }

    public function start(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== 'released') {
            throw new DomainException('Only released orders can be started.', 'PROD_ORDER_NOT_RELEASED', 422);
        }

        // Ensure all linked MRQs are fulfilled before production begins (PROD-MRQ-001).
        $unfulfilledMrq = \App\Domains\Inventory\Models\MaterialRequisition::query()
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
        if ($order->status !== 'in_progress') {
            throw new DomainException('Order is not in progress.', 'PROD_ORDER_NOT_IN_PROGRESS', 422);
        }

        if ((float) $order->qty_produced <= 0) {
            throw new DomainException(
                'No output has been logged. Log production output before completing the order.',
                'PROD_NO_OUTPUT_LOGGED',
                422,
            );
        }

        return DB::transaction(function () use ($order): ProductionOrder {
            $order->update(['status' => 'completed']);

            // Move finished goods into stock
            $location = WarehouseLocation::where('is_active', true)->first();
            if ($location !== null) {
                $systemUser = User::where('email', 'admin@ogamierp.local')->first();
                if ($systemUser !== null) {
                    $netQty = (float) $order->qty_produced - (float) $order->qty_rejected;
                    $this->stockService->receive(
                        itemId: $order->product_item_id,
                        locationId: $location->id,
                        quantity: $netQty,
                        referenceType: 'production_orders',
                        referenceId: $order->id,
                        actor: $systemUser,
                        remarks: "Auto-receive from WO {$order->po_reference}",
                    );
                }
            }

            // PROD-DEL-001: Notify Delivery domain AFTER the transaction commits so the
            // queued CreateDeliveryReceiptOnProductionComplete listener reads committed data.
            DB::afterCommit(fn () => ProductionOrderCompleted::dispatch($order->fresh()));

            return $order->refresh();
        });
    }

    public function cancel(ProductionOrder $order): ProductionOrder
    {
        if (! in_array($order->status, ['draft', 'released'], true)) {
            throw new DomainException('Only draft or released orders can be cancelled.', 'PROD_ORDER_CANNOT_CANCEL', 422);
        }

        return DB::transaction(function () use ($order): ProductionOrder {
            $mrqs = \App\Domains\Inventory\Models\MaterialRequisition::query()
                ->where('production_order_id', $order->id)
                ->get();

            /** @var \App\Models\User $actor */
            $actor = auth()->user() ?? \App\Models\User::findOrFail($order->created_by_id);

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
            $mrqs = \App\Domains\Inventory\Models\MaterialRequisition::query()
                ->where('production_order_id', $order->id)
                ->get();

            /** @var \App\Models\User $actor */
            $actor = auth()->user() ?? \App\Models\User::findOrFail($order->created_by_id);

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
        if (! in_array($order->status, ['released', 'in_progress'], true)) {
            throw new DomainException('Production order is not active.', 'PROD_ORDER_NOT_ACTIVE', 422);
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

        return $log->load('operator', 'recordedBy');
    }
}
