<?php

declare(strict_types=1);

namespace App\Http\Resources\Production;

use App\Http\Resources\Inventory\ItemMasterResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BomComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'component_item'     => new ItemMasterResource($this->whenLoaded('componentItem')),
            'component_item_id'  => $this->component_item_id,
            'qty_per_unit'       => $this->qty_per_unit,
            'unit_of_measure'    => $this->unit_of_measure,
            'scrap_factor_pct'   => $this->scrap_factor_pct,
        ];
    }
}
