<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use App\Domains\Inventory\Models\WarehouseLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WarehouseLocation */
final class WarehouseLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'code'          => $this->code,
            'name'          => $this->name,
            'zone'          => $this->zone,
            'bin'           => $this->bin,
            'department_id' => $this->department_id,
            'department'    => $this->whenLoaded('department', fn () => [
                'id'   => $this->department->id,
                'name' => $this->department->name,
            ]),
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
