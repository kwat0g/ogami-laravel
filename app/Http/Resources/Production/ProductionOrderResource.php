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
            'id' => $this->id,
            'ulid' => $this->ulid,
            'po_reference' => $this->po_reference,
            'delivery_schedule' => $this->whenLoaded('deliverySchedule', fn () => [
                'id' => $this->deliverySchedule->id,
                'ds_reference' => $this->deliverySchedule->ds_reference,
            ]),
            'product_item' => new ItemMasterResource($this->whenLoaded('productItem')),
            'bom' => new BomResource($this->whenLoaded('bom')),
            'qty_required' => $this->qty_required,
            'qty_produced' => $this->qty_produced,
            'progress_pct' => $this->resource->progressPct(),
            'standard_unit_cost_centavos' => $this->standard_unit_cost_centavos ?? 0,
            'estimated_total_cost_centavos' => $this->estimated_total_cost_centavos ?? 0,
            'target_start_date' => $this->target_start_date?->toDateString(),
            'target_end_date' => $this->target_end_date?->toDateString(),
            'status' => $this->status,
            'mrq_pending' => $this->status === 'released' && ($this->pending_mrq_count ?? 0) > 0,
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'output_logs' => ProductionOutputLogResource::collection($this->whenLoaded('outputLogs')),
            'inspections' => $this->whenLoaded('inspections', fn () => $this->inspections->map(fn ($i) => [
                'ulid' => $i->ulid,
                'stage' => $i->stage,
                'status' => $i->status,
                'inspection_date' => $i->inspection_date?->toDateString(),
                'qty_inspected' => (float) $i->qty_inspected,
                'qty_passed' => (float) $i->qty_passed,
                'qty_failed' => (float) $i->qty_failed,
            ])->toArray()),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
