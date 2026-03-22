<?php

declare(strict_types=1);

namespace App\Http\Resources\Procurement;

use App\Domains\Procurement\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrderItem */
final class PurchaseOrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pr_item_id' => $this->pr_item_id,
            'item_description' => $this->item_description,
            'unit_of_measure' => $this->unit_of_measure,
            'quantity_ordered' => (float) $this->quantity_ordered,
            'negotiated_quantity' => $this->negotiated_quantity !== null ? (float) $this->negotiated_quantity : null,
            'vendor_item_notes' => $this->vendor_item_notes,
            'agreed_unit_cost' => (float) $this->agreed_unit_cost,
            'total_cost' => (float) $this->total_cost,
            'quantity_received' => (float) $this->quantity_received,
            'quantity_pending' => (float) $this->quantity_pending,
            'line_order' => $this->line_order,
        ];
    }
}
