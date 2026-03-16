<?php

declare(strict_types=1);

namespace App\Http\Resources\Procurement;

use App\Domains\Procurement\Models\GoodsReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GoodsReceiptItem */
final class GoodsReceiptItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_item_id' => $this->po_item_id,
            'quantity_received' => (float) $this->quantity_received,
            'unit_of_measure' => $this->unit_of_measure,
            'condition' => $this->condition,
            'remarks' => $this->remarks,
        ];
    }
}
