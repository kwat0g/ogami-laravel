<?php

declare(strict_types=1);

namespace App\Http\Resources\QC;

use App\Domains\QC\Models\Inspection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Inspection */
final class InspectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'ulid'                   => $this->ulid,
            'inspection_reference'   => $this->inspection_reference,
            'stage'                  => $this->stage,
            'status'                 => $this->status,
            'qty_inspected'          => $this->qty_inspected,
            'qty_passed'             => $this->qty_passed,
            'qty_failed'             => $this->qty_failed,
            'inspection_date'        => $this->inspection_date?->toDateString(),
            'remarks'                => $this->remarks,
            'item_master'            => $this->whenLoaded('itemMaster', fn () => [
                'id'        => $this->itemMaster->id,
                'item_code' => $this->itemMaster->item_code,
                'name'      => $this->itemMaster->name,
            ]),
            'lot_batch'              => $this->whenLoaded('lotBatch', fn () => [
                'id'            => $this->lotBatch->id,
                'batch_number'  => $this->lotBatch->batch_number,
            ]),
            'inspector'              => $this->whenLoaded('inspector', fn () => [
                'id'   => $this->inspector->id,
                'name' => $this->inspector->full_name,
            ]),
            'template'               => $this->whenLoaded('template', fn () => new InspectionTemplateResource($this->template)),
            'results'                => $this->whenLoaded('results', fn () =>
                $this->results->map(fn ($r) => [
                    'id'                          => $r->id,
                    'inspection_template_item_id' => $r->inspection_template_item_id,
                    'criterion'                   => $r->criterion,
                    'actual_value'                => $r->actual_value,
                    'is_conforming'               => $r->is_conforming,
                    'remarks'                     => $r->remarks,
                ])
            ),
            'ncrs'                   => $this->whenLoaded('ncrs', fn () => NcrResource::collection($this->ncrs)),
            'deleted_at'             => $this->deleted_at?->toIso8601String(),
            'created_at'             => $this->created_at?->toIso8601String(),
        ];
    }
}
