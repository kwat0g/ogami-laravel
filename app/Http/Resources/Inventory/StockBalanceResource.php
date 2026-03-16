<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use App\Domains\Inventory\Models\StockBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockBalance */
final class StockBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'item_id' => $this->item_id,
            'location_id' => $this->location_id,
            'quantity_on_hand' => $this->quantity_on_hand,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'item' => $this->whenLoaded('item', fn () => [
                'id' => $this->item->id,
                'item_code' => $this->item->item_code,
                'name' => $this->item->name,
                'unit_of_measure' => $this->item->unit_of_measure,
                'reorder_point' => $this->item->reorder_point,
            ]),
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'code' => $this->location->code,
                'name' => $this->location->name,
            ]),
        ];
    }
}
