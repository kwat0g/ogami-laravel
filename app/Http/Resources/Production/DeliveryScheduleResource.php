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
            'id'                   => $this->id,
            'ulid'                 => $this->ulid,
            'ds_reference'         => $this->ds_reference,
            'customer'             => $this->whenLoaded('customer', fn () => [
                'id'   => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'product_item'         => new ItemMasterResource($this->whenLoaded('productItem')),
            'qty_ordered'          => $this->qty_ordered,
            'target_delivery_date' => $this->target_delivery_date?->toDateString(),
            'type'                 => $this->type,
            'status'               => $this->status,
            'notes'                => $this->notes,
            'production_orders'    => ProductionOrderResource::collection($this->whenLoaded('productionOrders')),
            'created_at'           => $this->created_at?->toIso8601String(),
        ];
    }
}
