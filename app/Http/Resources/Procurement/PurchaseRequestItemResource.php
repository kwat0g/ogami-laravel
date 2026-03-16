<?php

declare(strict_types=1);

namespace App\Http\Resources\Procurement;

use App\Domains\Procurement\Models\PurchaseRequestItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseRequestItem */
final class PurchaseRequestItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_item_id' => $this->vendor_item_id,
            'item_description' => $this->item_description,
            'unit_of_measure' => $this->unit_of_measure,
            'quantity' => (float) $this->quantity,
            'estimated_unit_cost' => (float) $this->estimated_unit_cost,
            'estimated_total' => (float) $this->estimated_total,
            'specifications' => $this->specifications,
            'line_order' => $this->line_order,
        ];
    }
}
