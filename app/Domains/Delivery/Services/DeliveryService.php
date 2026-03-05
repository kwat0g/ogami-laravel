<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Services;

use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Delivery\Models\Shipment;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class DeliveryService implements ServiceContract
{
    // ── Delivery Receipts ─────────────────────────────────────────────────

    public function paginateReceipts(array $filters = []): LengthAwarePaginator
    {
        return DeliveryReceipt::query()
            ->with(['vendor', 'customer', 'receivedBy'])
            ->when($filters['direction'] ?? null, fn ($q, $v) => $q->where('direction', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    public function storeReceipt(array $data, int $userId): DeliveryReceipt
    {
        return DB::transaction(function () use ($data, $userId): DeliveryReceipt {
            $receipt = DeliveryReceipt::create([
                'vendor_id'      => $data['vendor_id'] ?? null,
                'customer_id'    => $data['customer_id'] ?? null,
                'direction'      => $data['direction'] ?? 'inbound',
                'status'         => 'draft',
                'receipt_date'   => $data['receipt_date'],
                'remarks'        => $data['remarks'] ?? null,
                'received_by_id' => $data['received_by_id'] ?? $userId,
                'created_by_id'  => $userId,
            ]);

            foreach ($data['items'] ?? [] as $item) {
                $receipt->items()->create([
                    'item_master_id'    => $item['item_master_id'],
                    'quantity_expected' => $item['quantity_expected'] ?? 0,
                    'quantity_received' => $item['quantity_received'] ?? 0,
                    'unit_of_measure'   => $item['unit_of_measure'] ?? null,
                    'lot_batch_number'  => $item['lot_batch_number'] ?? null,
                    'remarks'           => $item['remarks'] ?? null,
                ]);
            }

            return $receipt->loadMissing(['items.itemMaster', 'vendor', 'customer']);
        });
    }

    public function confirmReceipt(DeliveryReceipt $receipt): DeliveryReceipt
    {
        if ($receipt->status !== 'draft') {
            throw new DomainException('DELIVERY_DR_NOT_DRAFT');
        }
        $receipt->update(['status' => 'confirmed']);
        return $receipt;
    }

    // ── Shipments ─────────────────────────────────────────────────────────

    public function paginateShipments(array $filters = []): LengthAwarePaginator
    {
        return Shipment::query()
            ->with(['deliveryReceipt', 'createdBy'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    public function storeShipment(array $data, int $userId): Shipment
    {
        return Shipment::create([
            'delivery_receipt_id' => $data['delivery_receipt_id'] ?? null,
            'carrier'             => $data['carrier'] ?? null,
            'tracking_number'     => $data['tracking_number'] ?? null,
            'shipped_at'          => $data['shipped_at'] ?? null,
            'estimated_arrival'   => $data['estimated_arrival'] ?? null,
            'actual_arrival'      => $data['actual_arrival'] ?? null,
            'status'              => $data['status'] ?? 'pending',
            'notes'               => $data['notes'] ?? null,
            'created_by_id'       => $userId,
        ]);
    }

    public function updateShipmentStatus(Shipment $shipment, string $status): Shipment
    {
        $shipment->update(['status' => $status]);
        return $shipment;
    }
}
