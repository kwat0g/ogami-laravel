<?php

declare(strict_types=1);

namespace App\Http\Resources\HR\Recruitment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PreEmploymentChecklistResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $progress = $this->completionProgress();

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'waiver_reason' => $this->waiver_reason,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'progress' => $progress,
            'requirements' => $this->whenLoaded('requirements', fn () => $this->requirements->map(fn ($r) => [
                'id' => $r->id,
                'requirement_type' => $r->requirement_type->value,
                'label' => $r->label,
                'is_required' => $r->is_required,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'status_color' => $r->status->color(),
                'document_path' => $r->document_path,
                'submitted_at' => $r->submitted_at?->toIso8601String(),
                'verified_at' => $r->verified_at?->toIso8601String(),
                'remarks' => $r->remarks,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
