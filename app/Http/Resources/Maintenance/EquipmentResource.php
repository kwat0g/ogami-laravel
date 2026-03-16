<?php

declare(strict_types=1);

namespace App\Http\Resources\Maintenance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EquipmentResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'equipment_code' => $this->equipment_code,
            'name' => $this->name,
            'category' => $this->category,
            'manufacturer' => $this->manufacturer,
            'model_number' => $this->model_number,
            'serial_number' => $this->serial_number,
            'location' => $this->location,
            'commissioned_on' => $this->commissioned_on?->toDateString(),
            'status' => $this->status,
            'is_active' => $this->is_active,
            'pm_schedules' => $this->whenLoaded('pmSchedules', fn () => $this->pmSchedules->map(fn ($s) => [
                'id' => $s->id,
                'task_name' => $s->task_name,
                'frequency_days' => $s->frequency_days,
                'last_done_on' => $s->last_done_on?->toDateString(),
                'next_due_on' => $s->next_due_on?->toDateString(),
            ])),
            'work_orders_count' => $this->whenCounted('workOrders'),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
