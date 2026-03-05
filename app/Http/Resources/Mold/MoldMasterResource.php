<?php

declare(strict_types=1);

namespace App\Http\Resources\Mold;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MoldMasterResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'ulid'                 => $this->ulid,
            'mold_code'            => $this->mold_code,
            'name'                 => $this->name,
            'description'          => $this->description,
            'cavity_count'         => $this->cavity_count,
            'material'             => $this->material,
            'location'             => $this->location,
            'max_shots'            => $this->max_shots,
            'current_shots'        => $this->current_shots,
            'is_critical'          => $this->isCritical(),
            'last_maintenance_at'  => $this->last_maintenance_at?->toISOString(),
            'status'               => $this->status,
            'is_active'            => $this->is_active,
            'shot_logs'            => $this->whenLoaded('shotLogs', fn () => $this->shotLogs->map(fn ($log) => [
                'id'         => $log->id,
                'shot_count' => $log->shot_count,
                'log_date'   => $log->log_date,
                'remarks'    => $log->remarks,
                'operator'   => $log->relationLoaded('operator') && $log->operator ? [
                    'id'   => $log->operator->id,
                    'name' => $log->operator->name,
                ] : null,
            ])),
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
