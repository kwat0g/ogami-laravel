<?php

declare(strict_types=1);

namespace App\Http\Resources\QC;

use App\Domains\QC\Models\InspectionTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InspectionTemplate */
final class InspectionTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'stage' => $this->stage,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'criterion' => $item->criterion,
                'method' => $item->method,
                'acceptable_range' => $item->acceptable_range,
                'sort_order' => $item->sort_order,
            ])
            ),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
