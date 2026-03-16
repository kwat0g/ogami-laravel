<?php

declare(strict_types=1);

namespace App\Http\Resources\AP;

use App\Domains\AP\Models\VendorItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VendorItem
 */
final class VendorItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var VendorItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'ulid' => $item->ulid,
            'vendor_id' => $item->vendor_id,
            'item_code' => $item->item_code,
            'item_name' => $item->item_name,
            'description' => $item->description,
            'unit_of_measure' => $item->unit_of_measure,
            'unit_price' => $item->unit_price,
            'unit_price_formatted' => number_format($item->unit_price / 100, 2),
            'is_active' => $item->is_active,
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }
}
