<?php

declare(strict_types=1);

namespace App\Http\Resources\Production;

use App\Http\Resources\Inventory\ItemMasterResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeliveryScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'ds_reference' => $this->ds_reference,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email ?? null,
            ]),
            'client_order_id' => $this->client_order_id,
            'client_order' => $this->whenLoaded('clientOrder', fn () => [
                'id' => $this->clientOrder->id,
                'order_reference' => $this->clientOrder->order_reference,
                'status' => $this->clientOrder->status,
            ]),
            // Legacy single-item fields (backward compat)
            'product_item' => new ItemMasterResource($this->whenLoaded('productItem')),
            'product_item_id' => $this->product_item_id,
            'qty_ordered' => $this->qty_ordered,
            // Multi-item children
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'ulid' => $item->ulid,
                'product_item_id' => $item->product_item_id,
                'product_item' => $item->relationLoaded('productItem') ? [
                    'id' => $item->productItem->id,
                    'item_code' => $item->productItem->item_code,
                    'name' => $item->productItem->name,
                    'unit_of_measure' => $item->productItem->unit_of_measure ?? null,
                ] : null,
                'qty_ordered' => $item->qty_ordered,
                'unit_price' => $item->unit_price,
                'status' => $item->status,
                'notes' => $item->notes,
                'production_orders' => $item->relationLoaded('productionOrders')
                    ? $item->productionOrders->map(fn ($po) => [
                        'id' => $po->id,
                        'ulid' => $po->ulid,
                        'po_reference' => $po->po_reference,
                        'status' => $po->status,
                        'qty_required' => $po->qty_required,
                        'qty_produced' => $po->qty_produced,
                        'target_start_date' => $po->target_start_date?->toDateString(),
                        'target_end_date' => $po->target_end_date?->toDateString(),
                    ])->toArray()
                    : null,
            ])->toArray()),
            'target_delivery_date' => $this->target_delivery_date?->toDateString(),
            'actual_delivery_date' => $this->actual_delivery_date?->toDateString(),
            'type' => $this->type,
            'status' => $this->status,
            'notes' => $this->notes,
            'delivery_address' => $this->delivery_address,
            'delivery_instructions' => $this->delivery_instructions,
            'total_items' => $this->total_items,
            'ready_items' => $this->ready_items,
            'missing_items' => $this->missing_items,
            'has_dispute' => $this->has_dispute ?? false,
            'item_status_summary' => $this->item_status_summary,
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'production_orders' => ProductionOrderResource::collection($this->whenLoaded('legacyProductionOrders')),
            'delivery_receipts' => $this->whenLoaded('deliveryReceipts', fn () => $this->deliveryReceipts->map(fn ($dr) => [
                'ulid' => $dr->ulid,
                'dr_reference' => $dr->dr_reference,
                'status' => $dr->status,
                'receipt_date' => $dr->receipt_date?->toDateString(),
            ])->toArray()),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
