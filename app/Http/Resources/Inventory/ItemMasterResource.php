<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use App\Domains\Inventory\Models\ItemMaster;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ItemMaster */
final class ItemMasterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'item_code' => $this->item_code,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'name' => $this->category->name,
            ]),
            'name' => $this->name,
            'unit_of_measure' => $this->unit_of_measure,
            'description' => $this->description,
            'reorder_point' => $this->reorder_point,
            'reorder_qty' => $this->reorder_qty,
            'type' => $this->type,
            'requires_iqc' => $this->requires_iqc,
            'is_active' => $this->is_active,
            'stock_balances' => $this->whenLoaded('stockBalances', fn () => StockBalanceResource::collection($this->stockBalances)
            ),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
