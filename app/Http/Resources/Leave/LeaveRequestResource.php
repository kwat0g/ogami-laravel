<?php

declare(strict_types=1);

namespace App\Http\Resources\Leave;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Leave\Models\LeaveRequest
 */
final class LeaveRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \App\Domains\Leave\Models\LeaveRequest $lr */
        $lr = $this->resource;

        return [
            'id' => $lr->id,
            'employee_id' => $lr->employee_id,
            'leave_type_id' => $lr->leave_type_id,
            'leave_type' => $this->whenLoaded('leaveType', fn () => [
                'id' => $lr->leaveType->id,
                'code' => $lr->leaveType->code,
                'name' => $lr->leaveType->name,
            ]),
            'submitted_by' => $lr->submitted_by,
            'date_from' => $lr->date_from->toDateString(),
            'date_to' => $lr->date_to->toDateString(),
            'total_days' => $lr->total_days,
            'is_half_day' => $lr->is_half_day,
            'half_day_period' => $lr->half_day_period,
            'reason' => $lr->reason,
            'requester_role' => $lr->requester_role,
            'status' => $lr->status,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $lr->employee->id,
                'employee_code' => $lr->employee->employee_code,
                'full_name' => $lr->employee->full_name,
            ]),
            'reviewed_by' => $lr->reviewed_by,
            'reviewer_remarks' => $lr->review_remarks,
            'reviewed_at' => $lr->reviewed_at?->toIso8601String(),
            'created_at' => $lr->created_at->toIso8601String(),
            'updated_at' => $lr->updated_at->toIso8601String(),
        ];
    }
}
