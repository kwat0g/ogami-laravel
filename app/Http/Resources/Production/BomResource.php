<?php

declare(strict_types=1);

namespace App\Http\Resources\Production;

use App\Http\Resources\Inventory\ItemMasterResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'ulid'           => $this->ulid,
            'product_item'   => new ItemMasterResource($this->whenLoaded('productItem')),
            'version'        => $this->version,
            'is_active'      => $this->is_active,
            'notes'          => $this->notes,
            'components'     => BomComponentResource::collection($this->whenLoaded('components')),
            'deleted_at'     => $this->deleted_at?->toIso8601String(),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
