<?php

declare(strict_types=1);

namespace App\Http\Resources\Production;

use App\Http\Resources\Inventory\ItemMasterResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductionOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'ulid'                 => $this->ulid,
            'po_reference'         => $this->po_reference,
            'delivery_schedule'    => $this->whenLoaded('deliverySchedule', fn () => [
                'id'           => $this->deliverySchedule->id,
                'ds_reference' => $this->deliverySchedule->ds_reference,
            ]),
            'product_item'         => new ItemMasterResource($this->whenLoaded('productItem')),
            'bom'                  => new BomResource($this->whenLoaded('bom')),
            'qty_required'         => $this->qty_required,
            'qty_produced'         => $this->qty_produced,
            'progress_pct'         => $this->resource->progressPct(),
            'target_start_date'    => $this->target_start_date?->toDateString(),
            'target_end_date'      => $this->target_end_date?->toDateString(),
            'status'               => $this->status,
            'notes'                => $this->notes,
            'created_by'           => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'output_logs'          => ProductionOutputLogResource::collection($this->whenLoaded('outputLogs')),
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
