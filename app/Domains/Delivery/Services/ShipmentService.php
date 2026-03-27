<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Services;

use App\Domains\Delivery\Models\Shipment;
use App\Events\Delivery\ShipmentDelivered;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Shipment Service — manages shipment tracking and status updates.
 *
 * Decomposed from the monolithic DeliveryService.
 */
final class ShipmentService implements ServiceContract
{
    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return Shipment::query()
            ->when($filters['with_archived'] ?? false, fn ($q) => $q->withTrashed())
            ->with(['deliveryReceipt', 'createdBy'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, int $userId): Shipment
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

    public function updateStatus(Shipment $shipment, string $status): Shipment
    {
        $validTransitions = [
            'pending' => ['in_transit', 'cancelled'],
            'in_transit' => ['delivered', 'cancelled'],
            'delivered' => [],
            'cancelled' => [],
        ];

        $allowed = $validTransitions[$shipment->status] ?? [];
        if (! in_array($status, $allowed, true)) {
            throw new DomainException(
                "Cannot transition shipment from '{$shipment->status}' to '{$status}'.",
                'SHIPMENT_INVALID_TRANSITION',
                422,
            );
        }

        $shipment->update([
            'status' => $status,
            'actual_arrival' => $status === 'delivered' ? now() : $shipment->actual_arrival,
        ]);

        if ($status === 'delivered') {
            event(new ShipmentDelivered($shipment->refresh()));
        }

        return $shipment;
    }

    /**
     * Get shipments in transit (for tracking dashboard).
     *
     * @return Collection<int, Shipment>
     */
    public function inTransit(): Collection
    {
        return Shipment::query()
            ->where('status', 'in_transit')
            ->with(['deliveryReceipt.customer', 'deliveryReceipt.vendor'])
            ->orderBy('estimated_arrival')
            ->get();
    }
}
