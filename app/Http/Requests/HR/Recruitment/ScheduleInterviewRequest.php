<?php

declare(strict_types=1);

namespace App\Http\Requests\HR\Recruitment;

use App\Domains\HR\Recruitment\Enums\InterviewType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ScheduleInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruitment.interviews.schedule');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'type' => ['required', 'string', Rule::in(InterviewType::values())],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'location' => ['nullable', 'string', 'max:500'],
            'interviewer_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:interviewer_department_id'],
            'interviewer_department_id' => ['nullable', 'integer', 'exists:departments,id', 'required_without:interviewer_id'],
            'round' => ['sometimes', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
