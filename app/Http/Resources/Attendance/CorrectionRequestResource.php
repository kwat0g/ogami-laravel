<?php

declare(strict_types=1);

namespace App\Http\Resources\Attendance;

use App\Domains\Attendance\Models\AttendanceCorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceCorrectionRequest
 */
final class CorrectionRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AttendanceCorrectionRequest $cr */
        $cr = $this->resource;

        return [
            'id' => $cr->id,
            'ulid' => $cr->ulid,
            'attendance_log_id' => $cr->attendance_log_id,
            'employee_id' => $cr->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => trim(
                ($cr->employee->first_name ?? '') . ' ' . ($cr->employee->last_name ?? ''),
            )),
            'correction_type' => $cr->correction_type,
            'requested_time_in' => $cr->requested_time_in?->toIso8601String(),
            'requested_time_out' => $cr->requested_time_out?->toIso8601String(),
            'requested_remarks' => $cr->requested_remarks,
            'reason' => $cr->reason,
            'supporting_document_path' => $cr->supporting_document_path,
            'status' => $cr->status,
            'reviewed_by' => $cr->reviewed_by,
            'reviewed_at' => $cr->reviewed_at?->toIso8601String(),
            'review_remarks' => $cr->review_remarks,
            'attendance_log' => $this->whenLoaded('attendanceLog', fn () => new AttendanceLogResource($cr->attendanceLog)),
            'created_at' => $cr->created_at?->toIso8601String(),
            'updated_at' => $cr->updated_at?->toIso8601String(),
        ];
    }
}
