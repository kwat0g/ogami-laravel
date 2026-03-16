<?php

declare(strict_types=1);

namespace App\Http\Resources\Maintenance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MaintenanceWorkOrderResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'mwo_reference' => $this->mwo_reference,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => $this->status,
            'title' => $this->title,
            'description' => $this->description,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'completion_notes' => $this->completion_notes,
            'labor_hours' => $this->labor_hours,
            'equipment' => $this->whenLoaded('equipment', fn () => [
                'id' => $this->equipment->id,
                'equipment_code' => $this->equipment->equipment_code,
                'name' => $this->equipment->name,
            ]),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ] : null),
            'reported_by' => $this->whenLoaded('reportedBy', fn () => $this->reportedBy ? [
                'id' => $this->reportedBy->id,
                'name' => $this->reportedBy->name,
            ] : null),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
