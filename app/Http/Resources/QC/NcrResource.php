<?php

declare(strict_types=1);

namespace App\Http\Resources\QC;

use App\Domains\QC\Models\NonConformanceReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NonConformanceReport */
final class NcrResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'ncr_reference' => $this->ncr_reference,
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity,
            'status' => $this->status,
            'inspection' => $this->whenLoaded('inspection', fn () => [
                'id' => $this->inspection->id,
                'inspection_reference' => $this->inspection->inspection_reference,
                'stage' => $this->inspection->stage,
                'item_master' => $this->inspection->relationLoaded('itemMaster') && $this->inspection->itemMaster
                    ? ['id' => $this->inspection->itemMaster->id, 'name' => $this->inspection->itemMaster->name]
                    : null,
            ]),
            'raised_by' => $this->whenLoaded('raisedBy', fn () => [
                'id' => $this->raisedBy->id,
                'name' => $this->raisedBy->name,
            ]),
            'capa_actions' => $this->whenLoaded('capaActions', fn () => $this->capaActions->map(fn ($c) => [
                'id' => $c->id,
                'ulid' => $c->ulid,
                'type' => $c->type,
                'description' => $c->description,
                'due_date' => $c->due_date?->toDateString(),
                'status' => $c->status,
                'assigned_to' => $c->relationLoaded('assignedTo') && $c->assignedTo
                    ? ['id' => $c->assignedTo->id, 'name' => $c->assignedTo->name]
                    : null,
                'completed_at' => $c->completed_at?->toIso8601String(),
            ])
            ),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
