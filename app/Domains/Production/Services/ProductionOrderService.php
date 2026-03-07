<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Inventory\Services\MaterialRequisitionService;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOutputLog;
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

    public function release(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== 'draft') {
            throw new DomainException('Only draft orders can be released.', 'PROD_ORDER_NOT_DRAFT', 422);
        }

        $order->update(['status' => 'released']);

        // PROD-002: Auto-generate a draft MRQ from the BOM for material planning
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
    }

    public function start(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== 'released') {
            throw new DomainException('Only released orders can be started.', 'PROD_ORDER_NOT_RELEASED', 422);
        }

        $order->update(['status' => 'in_progress']);

        return $order->refresh();
    }

    public function complete(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== 'in_progress') {
            throw new DomainException('Order is not in progress.', 'PROD_ORDER_NOT_IN_PROGRESS', 422);
        }

        $order->update(['status' => 'completed']);

        // Move finished goods into stock
        $location = WarehouseLocation::where('is_active', true)->first();
        if ($location !== null) {
            $systemUser = User::where('email', 'admin@ogamierp.local')->first();
            if ($systemUser !== null) {
                $this->stockService->receive(
                    itemId: $order->product_item_id,
                    locationId: $location->id,
                    quantity: (float) $order->qty_produced,
                    referenceType: ProductionOrder::class,
                    referenceId: $order->id,
                    actor: $systemUser,
                    remarks: "Auto-receive from WO {$order->po_reference}",
                );
            }
        }

        // PROD-DEL-001: Notify Delivery domain to draft an outbound DR for delivery-scheduled WOs.
        ProductionOrderCompleted::dispatch($order);

        return $order->refresh();
    }

    public function cancel(ProductionOrder $order): ProductionOrder
    {
        if (! in_array($order->status, ['draft', 'released'], true)) {
            throw new DomainException('Only draft or released orders can be cancelled.', 'PROD_ORDER_CANNOT_CANCEL', 422);
        }

        $order->update(['status' => 'cancelled']);

        return $order->refresh();
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
