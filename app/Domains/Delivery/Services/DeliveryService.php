<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Services;

use App\Domains\CRM\Models\ClientOrder;
use App\Domains\CRM\Models\ClientOrderActivity;
use App\Domains\CRM\StateMachines\ClientOrderStateMachine;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Delivery\Models\Shipment;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\DeliverySchedule;
use App\Domains\Sales\Models\SalesOrder;
use App\Events\Delivery\ShipmentDelivered;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DeliveryService implements ServiceContract
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    // ── Delivery Receipts ─────────────────────────────────────────────────

    public function paginateReceipts(array $filters = []): LengthAwarePaginator
    {
        return DeliveryReceipt::query()
            ->when($filters['with_archived'] ?? false, fn ($q) => $q->withTrashed())
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($q2) => $q2->where('dr_reference', 'ilike', "%{$v}%")->orWhereHas('customer', fn ($q3) => $q3->where('name', 'ilike', "%{$v}%"))->orWhereHas('vendor', fn ($q3) => $q3->where('name', 'ilike', "%{$v}%"))))
            ->with(['vendor', 'customer', 'receivedBy'])
            ->when($filters['direction'] ?? null, fn ($q, $v) => $q->where('direction', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    public function storeReceipt(array $data, int $userId): DeliveryReceipt
    {
        $direction = (string) ($data['direction'] ?? 'inbound');
        $customerId = $data['customer_id'] ?? null;
        $salesOrderId = $data['sales_order_id'] ?? null;
        $deliveryScheduleId = $data['delivery_schedule_id'] ?? null;
        $isInternalOutbound = false;

        // Outbound receipts must be linked for client deliveries.
        // Internal outbound receipts (no customer + no upstream refs) are allowed
        // for non-client/internal production movements.
        if ($direction === 'outbound') {
            $isInternalOutbound = $customerId === null && $salesOrderId === null && $deliveryScheduleId === null;
            if (! $isInternalOutbound) {
                if ($salesOrderId === null && $deliveryScheduleId === null) {
                    throw new DomainException(
                        'Outbound delivery receipts must reference a Sales Order or Delivery Schedule.',
                        'DELIVERY_REFERENCE_REQUIRED',
                        422,
                    );
                }
            }
        }

        // CHAIN-DR-001: Validate SO status for outbound DRs.
        if ($salesOrderId !== null) {
            $so = SalesOrder::find($salesOrderId);
            if ($so === null) {
                throw new DomainException('Sales order not found.', 'DELIVERY_SO_NOT_FOUND', 422);
            }
            if (! in_array($so->status, ['confirmed', 'in_production', 'partially_delivered'], true)) {
                throw new DomainException(
                    "Cannot create delivery receipt for sales order with status '{$so->status}'. Order must be confirmed first.",
                    'DELIVERY_SO_NOT_CONFIRMED',
                    422,
                );
            }

            if ($customerId === null && $so->customer_id !== null) {
                $customerId = (int) $so->customer_id;
            }
        }

        if ($deliveryScheduleId !== null) {
            $ds = DeliverySchedule::find($deliveryScheduleId);
            if ($ds === null) {
                throw new DomainException('Delivery schedule not found.', 'DELIVERY_SCHEDULE_NOT_FOUND', 422);
            }

            if ($direction === 'outbound' && ! in_array($ds->status, ['ready', 'partially_ready', 'dispatched'], true)) {
                throw new DomainException(
                    "Cannot create delivery receipt for delivery schedule in status '{$ds->status}'.",
                    'DELIVERY_SCHEDULE_NOT_READY',
                    422,
                );
            }

            if ($customerId !== null && (int) $ds->customer_id !== (int) $customerId) {
                throw new DomainException(
                    'Delivery receipt customer does not match the linked delivery schedule customer.',
                    'DELIVERY_CUSTOMER_MISMATCH',
                    422,
                );
            }

            if ($customerId === null && $ds->customer_id !== null) {
                $customerId = (int) $ds->customer_id;
            }
        }

        if ($direction === 'outbound' && ! $isInternalOutbound && $customerId === null) {
            throw new DomainException(
                'Outbound delivery receipts must specify a customer.',
                'DELIVERY_CUSTOMER_REQUIRED',
                422,
            );
        }

        return DB::transaction(function () use ($data, $userId, $customerId, $salesOrderId, $deliveryScheduleId): DeliveryReceipt {
            $receipt = DeliveryReceipt::create([
                'vendor_id' => $data['vendor_id'] ?? null,
                'customer_id' => $customerId,
                'delivery_schedule_id' => $deliveryScheduleId,
                'sales_order_id' => $salesOrderId,
                'direction' => $data['direction'] ?? 'inbound',
                'status' => 'draft',
                'receipt_date' => $data['receipt_date'],
                'remarks' => $data['remarks'] ?? null,
                'received_by_id' => $data['received_by_id'] ?? $userId,
                'created_by_id' => $userId,
            ]);

            foreach ($data['items'] ?? [] as $item) {
                $receipt->items()->create([
                    'item_master_id' => $item['item_master_id'],
                    'quantity_expected' => $item['quantity_expected'] ?? 0,
                    'quantity_received' => $item['quantity_received'] ?? 0,
                    'unit_of_measure' => $item['unit_of_measure'] ?? null,
                    'lot_batch_number' => $item['lot_batch_number'] ?? null,
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            return $receipt->loadMissing(['items.itemMaster', 'vendor', 'customer']);
        });
    }

    public function markDispatched(DeliveryReceipt $receipt, User $actor): DeliveryReceipt
    {
        if (! in_array($receipt->status, ['confirmed', 'partially_delivered'], true)) {
            throw new DomainException(
                "Cannot dispatch — receipt is in status '{$receipt->status}'.",
                'DELIVERY_INVALID_STATUS',
                422,
            );
        }

        $receipt->update(['status' => 'dispatched']);
        $this->syncLinkedDeliveryScheduleStatus($receipt, 'dispatched');

        return $receipt->refresh();
    }

    public function confirmReceipt(DeliveryReceipt $receipt, User $actor): DeliveryReceipt
    {
        if ($receipt->status !== 'draft') {
            throw new DomainException('Delivery receipt is not in draft status.', 'DELIVERY_DR_NOT_DRAFT', 422);
        }

        return DB::transaction(function () use ($receipt, $actor): DeliveryReceipt {
            $receipt->update(['status' => 'confirmed']);

            // FS-029 FIX: Require active warehouse — never silently skip stock movement.
            $receipt->loadMissing('items');
            $hasStockItems = $receipt->items->contains(fn ($item) => $item->item_master_id !== null && $item->quantity_received > 0);

            if ($hasStockItems) {
                $location = WarehouseLocation::where('is_active', true)->first();

                if ($location === null) {
                    throw new DomainException(
                        'Cannot confirm delivery receipt: no active warehouse location configured. Create at least one active warehouse location before confirming deliveries.',
                        'DELIVERY_NO_WAREHOUSE',
                        422,
                    );
                }

                // DEL-001: For outbound deliveries, issue stock for each confirmed item.
                if ($receipt->direction === 'outbound') {
                    foreach ($receipt->items as $item) {
                        if ($item->item_master_id === null || $item->quantity_received <= 0) {
                            continue;
                        }

                        $requiredQty = (float) $item->quantity_received;
                        $availableRows = StockBalance::query()
                            ->select('stock_balances.location_id', 'stock_balances.quantity_on_hand')
                            ->join('warehouse_locations', 'warehouse_locations.id', '=', 'stock_balances.location_id')
                            ->where('warehouse_locations.is_active', true)
                            ->where('stock_balances.item_id', $item->item_master_id)
                            ->where('stock_balances.quantity_on_hand', '>', 0)
                            ->orderByDesc('stock_balances.quantity_on_hand')
                            ->get();

                        $availableTotal = $availableRows->sum(fn ($row) => (float) $row->quantity_on_hand);

                        if ($availableTotal < $requiredQty) {
                            throw new DomainException(
                                sprintf(
                                    'Insufficient stock for item #%d. Required: %.4f, Available: %.4f across active warehouses.',
                                    $item->item_master_id,
                                    $requiredQty,
                                    $availableTotal,
                                ),
                                'INV_INSUFFICIENT_STOCK',
                                422,
                            );
                        }

                        $remaining = $requiredQty;

                        foreach ($availableRows as $row) {
                            if ($remaining <= 0.000001) {
                                break;
                            }

                            $locationQty = (float) $row->quantity_on_hand;
                            if ($locationQty <= 0) {
                                continue;
                            }

                            $issueQty = min($remaining, $locationQty);
                            if ($issueQty <= 0) {
                                continue;
                            }

                            $this->stockService->issue(
                                itemId: $item->item_master_id,
                                locationId: (int) $row->location_id,
                                quantity: $issueQty,
                                referenceType: 'delivery_receipts',
                                referenceId: $receipt->id,
                                actor: $actor,
                                remarks: "Outbound delivery DR#{$receipt->id}",
                            );

                            $remaining -= $issueQty;
                        }

                        if ($remaining > 0.000001) {
                            throw new DomainException(
                                sprintf(
                                    'Insufficient stock for item #%d after stock allocation. Remaining: %.4f.',
                                    $item->item_master_id,
                                    $remaining,
                                ),
                                'INV_INSUFFICIENT_STOCK',
                                422,
                            );
                        }
                    }
                }

                // DEL-002: For inbound deliveries (returned goods / supplier returns),
                // receive stock back into inventory for each confirmed item.
                elseif ($receipt->direction === 'inbound') {
                    foreach ($receipt->items as $item) {
                        if ($item->item_master_id === null || $item->quantity_received <= 0) {
                            continue;
                        }

                        $this->stockService->receive(
                            itemId: $item->item_master_id,
                            locationId: $location->id,
                            quantity: (float) $item->quantity_received,
                            referenceType: 'delivery_receipts',
                            referenceId: $receipt->id,
                            actor: $actor,
                            remarks: "Inbound delivery DR#{$receipt->id}",
                        );
                    }
                }
            }

            return $receipt->refresh();
        });
    }

    /**
     * Transition a confirmed delivery receipt to partially_delivered.
     * Used when some items are delivered but others are still pending.
     */
    public function markPartiallyDelivered(DeliveryReceipt $receipt, User $actor): DeliveryReceipt
    {
        if (! in_array($receipt->status, ['confirmed', 'dispatched'], true)) {
            throw new DomainException(
                "Cannot mark as partially delivered — receipt is in status '{$receipt->status}'.",
                'DELIVERY_INVALID_STATUS',
                422,
            );
        }

        $receipt->update(['status' => 'partially_delivered']);
        $this->syncLinkedDeliveryScheduleStatus($receipt, 'dispatched');

        return $receipt->refresh();
    }

    /**
     * Transition a confirmed or partially_delivered delivery receipt to delivered.
     * Marks the delivery as fully completed.
     */
    public function markDelivered(DeliveryReceipt $receipt, User $actor): DeliveryReceipt
    {
        if (! in_array($receipt->status, ['confirmed', 'dispatched', 'partially_delivered'], true)) {
            throw new DomainException(
                "Cannot mark as delivered — receipt is in status '{$receipt->status}'.",
                'DELIVERY_INVALID_STATUS',
                422,
            );
        }

        $receipt->update(['status' => 'delivered']);
        $this->syncLinkedDeliveryScheduleStatus($receipt, 'delivered');

        return $receipt->refresh();
    }

    private function syncLinkedDeliveryScheduleStatus(DeliveryReceipt $receipt, string $targetStatus): void
    {
        if ($receipt->delivery_schedule_id === null) {
            return;
        }

        $schedule = DeliverySchedule::find($receipt->delivery_schedule_id);
        if ($schedule === null) {
            return;
        }

        if ($targetStatus === 'dispatched' && in_array($schedule->status, ['ready', 'partially_ready', 'in_production'], true)) {
            $schedule->update(['status' => 'dispatched']);
        }

        if ($targetStatus === 'delivered') {
            $pending = DeliveryReceipt::query()
                ->where('delivery_schedule_id', $schedule->id)
                ->where('direction', 'outbound')
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->exists();

            $schedule->update(['status' => $pending ? 'dispatched' : 'delivered']);
        }

        // Propagate DS status change up to parent ClientOrder
        $this->syncClientOrderStatus($schedule->fresh());
    }

    /**
     * Aggregate all DeliverySchedule statuses for a ClientOrder and advance
     * the order through its state machine (approved -> ... -> delivered).
     */
    private function syncClientOrderStatus(DeliverySchedule $schedule): void
    {
        $clientOrder = $schedule->clientOrder;
        if ($clientOrder === null) {
            return;
        }

        // Only propagate if CO is in a state that allows forward delivery transitions
        if (! in_array($clientOrder->status, ['approved', 'in_production', 'ready_for_delivery'], true)) {
            return;
        }

        // Query ALL delivery schedules linked to this client order
        $allSchedules = DeliverySchedule::where('client_order_id', $clientOrder->id)->get();

        if ($allSchedules->isEmpty()) {
            return;
        }

        $statuses = $allSchedules->pluck('status')->toArray();
        $total = count($statuses);
        $deliveredCount = count(array_filter($statuses, fn (string $s): bool => $s === 'delivered'));
        $cancelledCount = count(array_filter($statuses, fn (string $s): bool => $s === 'cancelled'));
        $dispatchedOrLater = count(array_filter($statuses, fn (string $s): bool => in_array($s, ['dispatched', 'delivered'], true)));

        $activeTotal = $total - $cancelledCount;

        // All active DSs delivered -> CO delivered
        if ($activeTotal > 0 && $deliveredCount >= $activeTotal) {
            $this->transitionClientOrder($clientOrder, 'delivered');

            return;
        }

        // At least one DS dispatched/delivered -> CO ready_for_delivery
        if ($dispatchedOrLater > 0 && in_array($clientOrder->status, ['approved', 'in_production'], true)) {
            $this->transitionClientOrder($clientOrder, 'ready_for_delivery');
        }
    }

    /**
     * Walk the ClientOrder through intermediate states to reach the target.
     * Logs an activity entry for each transition.
     */
    private function transitionClientOrder(ClientOrder $clientOrder, string $targetStatus): void
    {
        $stateMachine = new ClientOrderStateMachine();
        $path = $this->resolveTransitionPath($clientOrder->status, $targetStatus);

        foreach ($path as $nextStatus) {
            if (! $stateMachine->isAllowed($clientOrder->status, $nextStatus)) {
                Log::warning('[DeliveryService] Cannot transition ClientOrder', [
                    'order_id' => $clientOrder->id,
                    'from' => $clientOrder->status,
                    'to' => $nextStatus,
                ]);

                return;
            }

            $previousStatus = $clientOrder->status;
            $stateMachine->transition($clientOrder, $nextStatus);
            $clientOrder->save();

            // Record activity for the client portal timeline
            ClientOrderActivity::create([
                'client_order_id' => $clientOrder->id,
                'user_id' => null, // system-generated transition
                'user_type' => 'system',
                'action' => 'status_changed',
                'from_status' => $previousStatus,
                'to_status' => $nextStatus,
                'comment' => 'Status updated automatically based on delivery progress.',
                'metadata' => ['triggered_by' => 'delivery_sync'],
            ]);
        }
    }

    /**
     * Compute the intermediate states needed to walk from $from to $to
     * along the forward delivery chain.
     *
     * @return list<string>
     */
    private function resolveTransitionPath(string $from, string $to): array
    {
        $chain = ['approved', 'in_production', 'ready_for_delivery', 'delivered', 'fulfilled'];
        $fromIdx = array_search($from, $chain, true);
        $toIdx = array_search($to, $chain, true);

        if ($fromIdx === false || $toIdx === false || $toIdx <= $fromIdx) {
            return [];
        }

        return array_slice($chain, $fromIdx + 1, $toIdx - $fromIdx);
    }

    // ── Shipments ─────────────────────────────────────────────────────────

    public function paginateShipments(array $filters = []): LengthAwarePaginator
    {
        return Shipment::query()
            ->when($filters['with_archived'] ?? false, fn ($q) => $q->withTrashed())
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($q2) => $q2->where('carrier', 'ilike', "%{$v}%")->orWhere('tracking_number', 'ilike', "%{$v}%")))
            ->with(['deliveryReceipt', 'createdBy'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    public function storeShipment(array $data, int $userId): Shipment
    {
        return Shipment::create([
            'delivery_receipt_id' => $data['delivery_receipt_id'] ?? null,
            'carrier' => $data['carrier'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
            'shipped_at' => $data['shipped_at'] ?? null,
            'estimated_arrival' => $data['estimated_arrival'] ?? null,
            'actual_arrival' => $data['actual_arrival'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'notes' => $data['notes'] ?? null,
            'created_by_id' => $userId,
        ]);
    }

    public function updateShipmentStatus(Shipment $shipment, string $status): Shipment
    {
        $shipment->update(['status' => $status]);

        if ($status === 'delivered') {
            event(new ShipmentDelivered($shipment->refresh()));
        }

        return $shipment;
    }
}
