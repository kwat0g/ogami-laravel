<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SalesOrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'item' => $this->whenLoaded('item', fn () => [
                'id' => $this->item->id,
                'name' => $this->item->name,
                'sku' => $this->item->sku ?? null,
            ]),
            'quantity' => $this->quantity,
            'unit_price_centavos' => $this->unit_price_centavos,
            'line_total_centavos' => $this->line_total_centavos,
            'remarks' => $this->remarks,
        ];
    }
}
