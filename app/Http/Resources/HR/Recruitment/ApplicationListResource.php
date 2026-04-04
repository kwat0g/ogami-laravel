<?php

declare(strict_types=1);

namespace App\Http\Resources\HR\Recruitment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApplicationListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $postingDepartment = $this->posting?->requisition?->department?->name ?? $this->posting?->department?->name;
        $postingPosition = $this->posting?->requisition?->position?->title ?? $this->posting?->position?->title;

        return [
            'ulid' => $this->ulid,
            'application_number' => $this->application_number,
            'candidate' => $this->whenLoaded('candidate', fn () => [
                'id' => $this->candidate->id,
                'full_name' => $this->candidate->full_name,
                'email' => $this->candidate->email,
            ]),
            'posting' => $this->whenLoaded('posting', fn () => [
                'ulid' => $this->posting->ulid,
                'title' => $this->posting->title,
                'department' => $postingDepartment,
                'position' => $postingPosition,
            ]),
            'source' => $this->source?->value,
            'source_label' => $this->source?->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'application_date' => (string) $this->application_date,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
