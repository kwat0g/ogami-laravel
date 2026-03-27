<?php

declare(strict_types=1);

namespace App\Http\Resources\CRM;

use App\Domains\CRM\Models\ClientOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClientOrderItem */
final class ClientOrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ClientOrderItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'item_master_id' => $item->item_master_id,
            'item_description' => $item->item_description,
            'quantity' => (float) $item->quantity,
            'unit_of_measure' => $item->unit_of_measure,
            'unit_price_centavos' => $item->unit_price_centavos,
            'line_total_centavos' => $item->line_total_centavos,
            'negotiated_quantity' => $item->negotiated_quantity ? (float) $item->negotiated_quantity : null,
            'negotiated_price_centavos' => $item->negotiated_price_centavos,
            'line_notes' => $item->line_notes,
            'line_order' => $item->line_order,

            'item_master' => $this->whenLoaded('itemMaster', fn () => $item->itemMaster ? [
                'id' => $item->itemMaster->id,
                'name' => $item->itemMaster->name,
                'sku' => $item->itemMaster->sku ?? null,
            ] : null),
        ];
    }
}
