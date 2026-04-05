<?php

declare(strict_types=1);

namespace App\Http\Resources\Leave;

use App\Domains\Leave\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveRequest
 */
final class LeaveRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var LeaveRequest $lr */
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
            'requester_type' => $lr->requester_type,
            'date_from' => $lr->date_from->toDateString(),
            'date_to' => $lr->date_to->toDateString(),
            'total_days' => $lr->total_days,
            'is_half_day' => $lr->is_half_day,
            'half_day_period' => $lr->half_day_period,
            'reason' => $lr->reason,
            'status' => $lr->status,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $lr->employee->id,
                'employee_code' => $lr->employee->employee_code,
                'full_name' => $lr->employee->full_name,
            ]),
            // Step 2 — Department Head
            'head_id' => $lr->head_id,
            'head_remarks' => $lr->head_remarks,
            'head_approved_at' => $lr->head_approved_at?->toIso8601String(),
            // Step 3 — Plant Manager
            'manager_approved_by' => $lr->manager_approved_by,
            'manager_approved_remarks' => $lr->manager_approved_remarks,
            'manager_approved_at' => $lr->manager_approved_at?->toIso8601String(),
            // Step 4 — HR Manager
            'hr_approved_by' => $lr->hr_approved_by,
            'hr_remarks' => $lr->hr_remarks,
            'hr_approved_at' => $lr->hr_approved_at?->toIso8601String(),
            // Final — Vice President
            'vp_id' => $lr->vp_id,
            'vp_remarks' => $lr->vp_remarks,
            'vp_noted_at' => $lr->vp_noted_at?->toIso8601String(),
            'created_at' => $lr->created_at->toIso8601String(),
            'updated_at' => $lr->updated_at->toIso8601String(),
        ];
    }
}
