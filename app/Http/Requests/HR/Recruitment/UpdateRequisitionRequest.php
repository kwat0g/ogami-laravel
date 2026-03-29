<?php

declare(strict_types=1);

namespace App\Http\Requests\HR\Recruitment;

use App\Domains\HR\Recruitment\Enums\EmploymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruitment.requisitions.edit');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'position_id' => ['sometimes', 'integer', 'exists:positions,id'],
            'employment_type' => ['sometimes', 'string', Rule::in(EmploymentType::values())],
            'headcount' => ['sometimes', 'integer', 'min:1'],
            'reason' => ['sometimes', 'string', 'max:2000'],
            'justification' => ['nullable', 'string', 'max:2000'],
            'salary_range_min' => ['nullable', 'integer', 'min:0'],
            'salary_range_max' => ['nullable', 'integer', 'min:0', 'gte:salary_range_min'],
            'target_start_date' => ['nullable', 'date', 'after:today'],
        ];
    }
}
