<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class QuotationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'quotation_number' => $this->quotation_number,
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
            'status' => $this->status,
            'total_centavos' => $this->total_centavos,
            'validity_date' => $this->validity_date,
            'notes' => $this->notes,
            'terms_and_conditions' => $this->terms_and_conditions,
            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
