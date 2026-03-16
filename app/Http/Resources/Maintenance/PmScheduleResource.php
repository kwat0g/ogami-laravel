<?php

declare(strict_types=1);

namespace App\Http\Resources\Maintenance;

use App\Domains\Maintenance\Models\PmSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MED-001: Standardized resource for PmSchedule.
 *
 * @mixin PmSchedule
 */
final class PmScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipment_id' => $this->equipment_id,
            'task_name' => $this->task_name,
            'frequency_days' => $this->frequency_days,
            'last_done_on' => $this->last_done_on?->toDateString(),
            'next_due_on' => $this->next_due_on?->toDateString(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
