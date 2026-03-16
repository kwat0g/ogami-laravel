<?php

declare(strict_types=1);

namespace App\Http\Resources\Maintenance;

use App\Domains\Maintenance\Models\MaintenanceWorkOrderPart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MED-001: Standardized resource for MaintenanceWorkOrderPart.
 *
 * @mixin MaintenanceWorkOrderPart
 */
final class MaintenanceWorkOrderPartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maintenance_work_order_id' => $this->maintenance_work_order_id,
            'item_id' => $this->item_id,
            'item' => $this->whenLoaded('item', fn () => [
                'id' => $this->item->id,
                'item_code' => $this->item->item_code,
                'name' => $this->item->name,
                'unit_of_measure' => $this->item->unit_of_measure,
            ]),
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'name' => $this->location->name,
                'code' => $this->location->code,
            ]),
            'quantity' => $this->quantity,
            'quantity_issued' => $this->quantity_issued,
            'quantity_returned' => $this->quantity_returned,
            'unit_cost_centavos' => $this->unit_cost_centavos,
            'unit_cost' => $this->unit_cost_centavos / 100,
            'total_cost_centavos' => $this->total_cost_centavos,
            'total_cost' => $this->total_cost_centavos / 100,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'issued_by_id' => $this->issued_by_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
