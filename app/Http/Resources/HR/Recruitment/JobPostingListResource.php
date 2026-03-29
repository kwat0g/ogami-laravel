<?php

declare(strict_types=1);

namespace App\Http\Resources\HR\Recruitment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class JobPostingListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'posting_number' => $this->posting_number,
            'title' => $this->title,
            'location' => $this->location,
            'employment_type' => $this->employment_type?->value,
            'is_internal' => $this->is_internal,
            'is_external' => $this->is_external,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'published_at' => $this->published_at?->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'views_count' => $this->views_count,
            'applications_count' => $this->whenCounted('applications'),
            'requisition' => $this->whenLoaded('requisition', fn () => [
                'ulid' => $this->requisition->ulid,
                'requisition_number' => $this->requisition->requisition_number,
                'department' => $this->requisition->department?->name,
                'position' => $this->requisition->position?->title,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
