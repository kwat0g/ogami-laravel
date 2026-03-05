<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

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
}
