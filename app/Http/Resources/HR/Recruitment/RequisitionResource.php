<?php

declare(strict_types=1);

namespace App\Http\Resources\HR\Recruitment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RequisitionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'requisition_number' => $this->requisition_number,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'code' => $this->department->code,
                'name' => $this->department->name,
            ]),
            'position' => $this->whenLoaded('position', fn () => [
                'id' => $this->position->id,
                'code' => $this->position->code,
                'title' => $this->position->title,
            ]),
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
            ]),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),
            'employment_type' => $this->employment_type?->value,
            'employment_type_label' => $this->employment_type?->label(),
            'headcount' => $this->headcount,
            'reason' => $this->reason,
            'justification' => $this->justification,
            'salary_range_min' => $this->salary_range_min,
            'salary_range_max' => $this->salary_range_max,
            'target_start_date' => $this->target_start_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'hired_count' => $this->hiredCount(),
            'postings' => JobPostingListResource::collection($this->whenLoaded('postings')),
            'approvals' => $this->whenLoaded('approvals', fn () => $this->approvals->map(fn ($a) => [
                'id' => $a->id,
                'user' => ['id' => $a->user->id, 'name' => $a->user->name],
                'action' => $a->action,
                'remarks' => $a->remarks,
                'acted_at' => $a->acted_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
