<?php

declare(strict_types=1);

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\Quotation;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderItem;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class SalesOrderService implements ServiceContract
{
    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = SalesOrder::with(['customer', 'contact', 'createdBy'])
            ->orderByDesc('id');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($data, $actor): SalesOrder {
            $order = SalesOrder::create([
                'order_number' => $data['order_number'] ?? 'SO-' . now()->format('Ymd-His'),
                'customer_id' => $data['customer_id'],
                'contact_id' => $data['contact_id'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'status' => 'draft',
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'promised_delivery_date' => $data['promised_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_id' => $actor->id,
            ]);

            $total = 0;
            foreach ($data['items'] ?? [] as $item) {
                $lineTotal = (int) round((float) $item['quantity'] * $item['unit_price_centavos']);
                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit_price_centavos' => $item['unit_price_centavos'],
                    'line_total_centavos' => $lineTotal,
                    'remarks' => $item['remarks'] ?? null,
                ]);
                $total += $lineTotal;
            }

            $order->update(['total_centavos' => $total]);

            return $order->load('items.item', 'customer', 'contact');
        });
    }

    /**
     * Create a Sales Order from an accepted Quotation.
     */
    public function createFromQuotation(Quotation $quotation, User $actor): SalesOrder
    {
        if ($quotation->status !== 'accepted') {
            throw new DomainException(
                'Quotation must be accepted before converting to sales order.',
                'SALES_QUOTATION_NOT_ACCEPTED',
                422
            );
        }

        return DB::transaction(function () use ($quotation, $actor): SalesOrder {
            $quotation->loadMissing('items');

            $items = $quotation->items->map(fn ($qi) => [
                'item_id' => $qi->item_id,
                'quantity' => $qi->quantity,
                'unit_price_centavos' => $qi->unit_price_centavos,
                'remarks' => $qi->remarks,
            ])->toArray();

            $order = $this->store([
                'customer_id' => $quotation->customer_id,
                'contact_id' => $quotation->contact_id,
                'quotation_id' => $quotation->id,
                'opportunity_id' => $quotation->opportunity_id,
                'notes' => "Created from quotation #{$quotation->quotation_number}",
                'items' => $items,
            ], $actor);

            $quotation->update(['status' => 'converted_to_order']);

            return $order;
        });
    }

    public function confirm(SalesOrder $order, User $approver): SalesOrder
    {
        if ($order->status !== 'draft') {
            throw new DomainException('Sales order must be in draft to confirm.', 'SALES_INVALID_ORDER_STATUS', 422);
        }

        $order->update([
            'status' => 'confirmed',
            'approved_by_id' => $approver->id,
            'approved_at' => now(),
        ]);

        return $order->fresh(['items.item', 'customer']) ?? $order;
    }

    public function cancel(SalesOrder $order): SalesOrder
    {
        if (in_array($order->status, ['delivered', 'invoiced', 'cancelled'], true)) {
            throw new DomainException('Cannot cancel order in current status.', 'SALES_INVALID_ORDER_STATUS', 422);
        }

        $order->update(['status' => 'cancelled']);

        return $order->fresh() ?? $order;
    }
}
