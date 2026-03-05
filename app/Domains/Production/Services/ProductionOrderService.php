<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Services\StockService;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOutputLog;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ProductionOrderService implements ServiceContract
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * @param array<string,mixed> $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = ProductionOrder::with('productItem', 'bom', 'createdBy', 'deliverySchedule')
            ->orderByDesc('id');

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
            'product_item_id'      => $data['product_item_id'],
            'bom_id'               => $data['bom_id'],
            'qty_required'         => $data['qty_required'],
            'target_start_date'    => $data['target_start_date'],
            'target_end_date'      => $data['target_end_date'],
            'status'               => 'draft',
            'notes'                => $data['notes'] ?? null,
            'created_by_id'        => $user->id,
        ]);

        return $order->load('productItem', 'bom', 'createdBy');
    }

    public function release(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== 'draft') {
            throw new DomainException('PROD_ORDER_NOT_DRAFT', 'Only draft orders can be released.');
        }

        $order->update(['status' => 'released']);

        return $order->refresh();
    }

    public function start(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== 'released') {
            throw new DomainException('PROD_ORDER_NOT_RELEASED', 'Only released orders can be started.');
        }

        $order->update(['status' => 'in_progress']);

        return $order->refresh();
    }

    public function complete(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== 'in_progress') {
            throw new DomainException('PROD_ORDER_NOT_IN_PROGRESS', 'Order is not in progress.');
        }

        $order->update(['status' => 'completed']);

        // Move finished goods into stock
        $location = WarehouseLocation::where('is_active', true)->first();
        if ($location !== null) {
            $systemUser = User::where('email', 'admin@ogamierp.local')->first();
            if ($systemUser !== null) {
                $this->stockService->receive(
                    itemId:        $order->product_item_id,
                    locationId:    $location->id,
                    qty:           (float) $order->qty_produced,
                    referenceType: ProductionOrder::class,
                    referenceId:   $order->id,
                    remarks:       "Auto-receive from WO {$order->po_reference}",
                    actorId:       $systemUser->id,
                );
            }
        }

        return $order->refresh();
    }

    public function cancel(ProductionOrder $order): ProductionOrder
    {
        if (! in_array($order->status, ['draft', 'released'], true)) {
            throw new DomainException('PROD_ORDER_CANNOT_CANCEL', 'Only draft or released orders can be cancelled.');
        }

        $order->update(['status' => 'cancelled']);

        return $order->refresh();
    }

    /** @param array<string,mixed> $data */
    public function logOutput(ProductionOrder $order, array $data, User $user): ProductionOutputLog
    {
        if (! in_array($order->status, ['released', 'in_progress'], true)) {
            throw new DomainException('PROD_ORDER_NOT_ACTIVE', 'Production order is not active.');
        }

        /** @var ProductionOutputLog $log */
        $log = ProductionOutputLog::create([
            'production_order_id' => $order->id,
            'shift'               => $data['shift'],
            'log_date'            => $data['log_date'],
            'qty_produced'        => $data['qty_produced'],
            'qty_rejected'        => $data['qty_rejected'] ?? 0,
            'operator_id'         => $data['operator_id'],
            'recorded_by_id'      => $user->id,
            'remarks'             => $data['remarks'] ?? null,
        ]);

        return $log->load('operator', 'recordedBy');
    }
}
