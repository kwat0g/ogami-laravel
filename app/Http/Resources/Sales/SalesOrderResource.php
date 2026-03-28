<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SalesOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'order_number' => $this->order_number,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'ulid' => $this->customer->ulid ?? null,
                'name' => $this->customer->name,
            ]),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'id' => $this->contact->id,
                'first_name' => $this->contact->first_name ?? null,
                'last_name' => $this->contact->last_name ?? null,
            ] : null),
            'quotation' => $this->whenLoaded('quotation', fn () => $this->quotation ? [
                'id' => $this->quotation->id,
                'ulid' => $this->quotation->ulid ?? null,
                'quotation_number' => $this->quotation->quotation_number,
            ] : null),
            'status' => $this->status,
            'total_centavos' => $this->total_centavos,
            'requested_delivery_date' => $this->requested_delivery_date,
            'promised_delivery_date' => $this->promised_delivery_date,
            'notes' => $this->notes,
            'items' => SalesOrderItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
