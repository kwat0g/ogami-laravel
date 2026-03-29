<?php

declare(strict_types=1);

namespace App\Http\Resources\HR\Recruitment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class JobPostingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'posting_number' => $this->posting_number,
            'title' => $this->title,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'location' => $this->location,
            'employment_type' => $this->employment_type?->value,
            'employment_type_label' => $this->employment_type?->label(),
            'is_internal' => $this->is_internal,
            'is_external' => $this->is_external,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'published_at' => $this->published_at?->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'views_count' => $this->views_count,
            'requisition' => $this->whenLoaded('requisition', fn () => new RequisitionListResource($this->requisition)),
            'applications' => ApplicationListResource::collection($this->whenLoaded('applications')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
