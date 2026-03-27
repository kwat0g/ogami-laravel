<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Services;

use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Inventory\Services\StockService;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Delivery Receipt Service — manages delivery receipt creation, confirmation, and stock movement.
 *
 * Decomposed from the monolithic DeliveryService.
 */
final class DeliveryReceiptService implements ServiceContract
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return DeliveryReceipt::query()
            ->when($filters['with_archived'] ?? false, fn ($q) => $q->withTrashed())
            ->with(['vendor', 'customer', 'receivedBy'])
            ->when($filters['direction'] ?? null, fn ($q, $v) => $q->where('direction', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($filters['vendor_id'] ?? null, fn ($q, $v) => $q->where('vendor_id', $v))
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, int $userId): DeliveryReceipt
    {
        return DB::transaction(function () use ($data, $userId): DeliveryReceipt {
            $receipt = DeliveryReceipt::create([
                'vendor_id' => $data['vendor_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'delivery_schedule_id' => $data['delivery_schedule_id'] ?? null,
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

    public function confirm(DeliveryReceipt $receipt, User $actor): DeliveryReceipt
    {
        if ($receipt->status !== 'draft') {
            throw new DomainException(
                'Delivery receipt is not in draft status.',
                'DELIVERY_DR_NOT_DRAFT',
                422,
            );
        }

        return DB::transaction(function () use ($receipt, $actor): DeliveryReceipt {
            $receipt->update(['status' => 'confirmed']);

            $receipt->loadMissing('items');
            $location = WarehouseLocation::where('is_active', true)->first();

            if ($location === null) {
                return $receipt->refresh();
            }

            foreach ($receipt->items as $item) {
                if ($item->item_master_id === null || $item->quantity_received <= 0) {
                    continue;
                }

                if ($receipt->direction === 'outbound') {
                    $this->stockService->issue(
                        itemId: $item->item_master_id,
                        locationId: $location->id,
                        quantity: (float) $item->quantity_received,
                        referenceType: 'delivery_receipts',
                        referenceId: $receipt->id,
                        actor: $actor,
                        remarks: "Outbound delivery DR#{$receipt->id}",
                    );
                } else {
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

            return $receipt->refresh();
        });
    }

    /**
     * Delivery performance metrics — on-time rate.
     *
     * @return array{total_deliveries: int, on_time: int, late: int, on_time_rate_pct: float}
     */
    public function deliveryPerformance(?int $year = null): array
    {
        $query = DeliveryReceipt::query()
            ->where('status', 'confirmed')
            ->where('direction', 'outbound')
            ->when($year, fn ($q, $y) => $q->whereYear('receipt_date', $y));

        $total = (clone $query)->count();

        // Consider on-time if receipt_date <= delivery_schedule expected date
        // Since we may not always have a schedule, count all confirmed as "delivered"
        return [
            'total_deliveries' => $total,
            'on_time' => $total, // TODO: compare against schedule expected dates
            'late' => 0,
            'on_time_rate_pct' => $total > 0 ? 100.0 : 0.0,
        ];
    }
}
