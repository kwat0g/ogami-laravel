<?php

declare(strict_types=1);

namespace App\Http\Resources\Delivery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeliveryReceiptResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'dr_reference' => $this->dr_reference,
            'direction' => $this->direction,
            'status' => $this->status,
            'receipt_date' => $this->receipt_date?->toDateString(),
            'remarks' => $this->remarks,
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor ? [
                'id' => $this->vendor->id,
                'name' => $this->vendor->name,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
            'received_by' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy ? [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'item_master_id' => $i->item_master_id,
                'item_name' => $i->relationLoaded('itemMaster') ? $i->itemMaster?->name : null,
                'quantity_expected' => $i->quantity_expected,
                'quantity_received' => $i->quantity_received,
                'unit_of_measure' => $i->unit_of_measure,
                'lot_batch_number' => $i->lot_batch_number,
                'remarks' => $i->remarks,
            ])),
            'shipments_count' => $this->whenCounted('shipments'),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
