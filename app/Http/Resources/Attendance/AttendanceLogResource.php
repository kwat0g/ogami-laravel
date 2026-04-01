<?php

declare(strict_types=1);

namespace App\Http\Resources\Attendance;

use App\Domains\Attendance\Models\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Attendance\WorkLocationResource;
use Carbon\CarbonInterface;

/**
 * @mixin AttendanceLog
 */
final class AttendanceLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AttendanceLog $log */
        $log = $this->resource;

        return [
            'id' => $log->id,
            'employee_id' => $log->employee_id,
            'work_date' => $log->work_date->toDateString(),
            'time_in' => $this->formatTimeValue($log->time_in),
            'time_out' => $this->formatTimeValue($log->time_out),
            'source' => $log->source,
            'worked_minutes' => $log->worked_minutes,
            'worked_hours' => $log->workedHours(),
            'late_minutes' => $log->late_minutes,
            'undertime_minutes' => $log->undertime_minutes,
            'overtime_minutes' => $log->overtime_minutes,
            'overtime_hours' => $log->overtimeHours(),
            'nights_diff_minutes' => $log->night_diff_minutes,
            'is_present' => $log->is_present,
            'is_absent' => $log->is_absent,
            'is_rest_day' => $log->is_rest_day,
            'is_holiday' => $log->is_holiday,
            'holiday_type' => $log->holiday_type,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $log->employee->id,
                'ulid' => $log->employee->ulid,
                'employee_code' => $log->employee->employee_code,
                'full_name' => $log->employee->full_name,
            ]),
            'remarks' => $log->remarks,
            'import_batch_id' => $log->import_batch_id,
            // Geolocation fields
            'attendance_status' => $log->attendance_status,
            'is_flagged' => $log->is_flagged ?? false,
            'flag_reason' => $log->flag_reason,
            'time_in_within_geofence' => $log->time_in_within_geofence,
            'time_in_distance_meters' => $log->time_in_distance_meters ? (float) $log->time_in_distance_meters : null,
            'time_in_accuracy_meters' => $log->time_in_accuracy_meters ? (float) $log->time_in_accuracy_meters : null,
            'time_out_within_geofence' => $log->time_out_within_geofence,
            'time_out_distance_meters' => $log->time_out_distance_meters ? (float) $log->time_out_distance_meters : null,
            'work_location' => $this->whenLoaded('workLocation', fn () => $log->workLocation
                ? new WorkLocationResource($log->workLocation)
                : null),
            'correction_note' => $log->correction_note,
            'corrected_at' => $log->corrected_at?->toIso8601String(),
            'created_at' => $log->created_at->toIso8601String(),
            'updated_at' => $log->updated_at->toIso8601String(),
        ];
    }

    private function formatTimeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('H:i:s');
        }

        $value = (string) $value;

        return str_contains($value, ' ')
            ? substr($value, 11, 8)
            : substr($value, 0, 8);
    }
}
