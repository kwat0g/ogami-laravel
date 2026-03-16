<?php

declare(strict_types=1);

namespace App\Http\Resources\Delivery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ShipmentResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'shipment_reference' => $this->shipment_reference,
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'shipped_at' => $this->shipped_at?->toISOString(),
            'estimated_arrival' => $this->estimated_arrival?->toDateString(),
            'actual_arrival' => $this->actual_arrival?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'delivery_receipt' => $this->whenLoaded('deliveryReceipt', fn () => $this->deliveryReceipt ? [
                'id' => $this->deliveryReceipt->id,
                'dr_reference' => $this->deliveryReceipt->dr_reference,
            ] : null),
            'impex_documents' => $this->whenLoaded('impexDocuments', fn () => $this->impexDocuments->map(fn ($d) => [
                'id' => $d->id,
                'document_type' => $d->document_type,
                'document_number' => $d->document_number,
                'issued_date' => $d->issued_date?->toDateString(),
                'expiry_date' => $d->expiry_date?->toDateString(),
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
