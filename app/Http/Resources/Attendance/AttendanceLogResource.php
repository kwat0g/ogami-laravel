<?php

declare(strict_types=1);

namespace App\Http\Resources\Attendance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Attendance\Models\AttendanceLog
 */
final class AttendanceLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \App\Domains\Attendance\Models\AttendanceLog $log */
        $log = $this->resource;

        return [
            'id' => $log->id,
            'employee_id' => $log->employee_id,
            'work_date' => $log->work_date->toDateString(),
            'time_in' => $log->time_in ? (str_contains($log->time_in, ' ') ? substr($log->time_in, 11, 8) : substr($log->time_in, 0, 8)) : null,
            'time_out' => $log->time_out ? (str_contains($log->time_out, ' ') ? substr($log->time_out, 11, 8) : substr($log->time_out, 0, 8)) : null,
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
                'employee_code' => $log->employee->employee_code,
                'full_name' => $log->employee->full_name,
            ]),
            'remarks' => $log->remarks,
            'import_batch_id' => $log->import_batch_id,
            'created_at' => $log->created_at->toIso8601String(),
            'updated_at' => $log->updated_at->toIso8601String(),
        ];
    }
}
