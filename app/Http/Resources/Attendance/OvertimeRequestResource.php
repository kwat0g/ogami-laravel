<?php

declare(strict_types=1);

namespace App\Http\Resources\Attendance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Attendance\Models\OvertimeRequest
 */
final class OvertimeRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \App\Domains\Attendance\Models\OvertimeRequest $ot */
        $ot = $this->resource;

        return [
            'id' => $ot->id,
            'employee_id' => $ot->employee_id,
            'requester_role' => $ot->requester_role,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $ot->employee->id,
                'employee_code' => $ot->employee->employee_code,
                'full_name' => $ot->employee->full_name,
            ]),
            'work_date' => $ot->work_date->toDateString(),
            'ot_start_time' => $ot->ot_start_time ? substr((string) $ot->ot_start_time, 0, 5) : null,
            'ot_end_time' => $ot->ot_end_time ? substr((string) $ot->ot_end_time, 0, 5) : null,
            'requested_minutes' => $ot->requested_minutes,
            'requested_hours' => $ot->requestedHours(),
            'approved_minutes' => $ot->approved_minutes,
            'approved_hours' => $ot->approved_minutes !== null ? round($ot->approved_minutes / 60, 2) : null,
            'reason' => $ot->reason,
            'status' => $ot->status,
            // Supervisor endorsement (staff requests)
            'supervisor_id' => $ot->supervisor_id,
            'supervisor_remarks' => $ot->supervisor_remarks,
            'supervisor_approved_at' => $ot->supervisor_approved_at?->toIso8601String(),
            // Manager final approval
            'approved_by' => $ot->approved_by,
            'approver_remarks' => $ot->approver_remarks,
            'reviewed_at' => $ot->reviewed_at?->toIso8601String(),
            // Executive approval (manager requests)
            'executive_id' => $ot->executive_id,
            'executive_remarks' => $ot->executive_remarks,
            'executive_approved_at' => $ot->executive_approved_at?->toIso8601String(),
            'created_at' => $ot->created_at->toIso8601String(),
            'updated_at' => $ot->updated_at->toIso8601String(),
        ];
    }
}
