<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\DeliverySchedule;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Pagination\LengthAwarePaginator;

final class DeliveryScheduleService implements ServiceContract
{
    /**
     * @param array<string,mixed> $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = DeliverySchedule::with('customer', 'productItem')
            ->orderBy('target_delivery_date');

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
            'customer_id'          => $data['customer_id'],
            'product_item_id'      => $data['product_item_id'],
            'qty_ordered'          => $data['qty_ordered'],
            'target_delivery_date' => $data['target_delivery_date'],
            'type'                 => $data['type']  ?? 'local',
            'notes'                => $data['notes'] ?? null,
        ]);

        return $ds->load('customer', 'productItem');
    }

    /** @param array<string,mixed> $data */
    public function update(DeliverySchedule $ds, array $data): DeliverySchedule
    {
        $ds->update(array_filter([
            'qty_ordered'          => $data['qty_ordered']          ?? null,
            'target_delivery_date' => $data['target_delivery_date'] ?? null,
            'type'                 => $data['type']                 ?? null,
            'status'               => $data['status']               ?? null,
            'notes'                => $data['notes']                ?? null,
        ], fn ($v) => $v !== null));

        return $ds->refresh();
    }
}
