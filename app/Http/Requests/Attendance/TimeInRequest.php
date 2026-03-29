<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

final class TimeInRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Every authenticated user with a linked employee record can clock in.
        // The attendance.time_clock permission is not checked here because
        // clocking in is a universal employee feature, not a role-specific one.
        return $this->user()?->employee_id !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['required', 'numeric', 'min:0', 'max:1000'],
            'device_info' => ['nullable', 'array'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
