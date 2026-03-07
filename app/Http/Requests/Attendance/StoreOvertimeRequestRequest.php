<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreOvertimeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'ot_start_time' => ['required', 'date_format:H:i'],
            'ot_end_time' => ['required', 'date_format:H:i', 'after:ot_start_time'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'ot_start_time.date_format' => 'OT start time must be in HH:MM format.',
            'ot_end_time.date_format' => 'OT end time must be in HH:MM format.',
            'ot_end_time.after' => 'OT end time must be after the start time.',
        ];
    }

    /**
     * OT-001/OT-002: Enforce 30-minute minimum and 4-hour maximum OT duration.
     * Handles midnight-crossing shifts (e.g., 22:00 → 02:00).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $start = $this->input('ot_start_time');
            $end = $this->input('ot_end_time');

            if (! $start || ! $end) {
                return;
            }

            $startSeconds = strtotime('1970-01-01 '.$start.':00');
            $endSeconds = strtotime('1970-01-01 '.$end.':00');

            // Handle midnight crossing (e.g., 22:00 → 02:00 is 4 hours)
            if ($endSeconds <= $startSeconds) {
                $endSeconds += 86400;
            }

            $minutes = (int) round(($endSeconds - $startSeconds) / 60);

            if ($minutes < 30) {
                $v->errors()->add('ot_end_time', 'OT duration must be at least 30 minutes. (OT-001)');
            } elseif ($minutes > 240) {
                $v->errors()->add('ot_end_time', 'OT duration cannot exceed 4 hours (240 minutes). (OT-002)');
            }
        });
    }
}
