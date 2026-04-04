<?php

declare(strict_types=1);

namespace App\Http\Requests\HR\Recruitment;

use App\Domains\HR\Recruitment\Enums\EmploymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreJobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $isHrManager = $user->hasRole('manager')
            && $user->departments()->where('code', 'HR')->exists();

        return $isHrManager || $user->can('recruitment.postings.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'job_requisition_id' => ['nullable', 'integer', 'exists:job_requisitions,id'],
            'department_id' => ['required_without:job_requisition_id', 'integer', 'exists:departments,id'],
            'position_id' => ['required_without:job_requisition_id', 'integer', 'exists:positions,id'],
            'salary_grade_id' => ['required_without:job_requisition_id', 'integer', 'exists:salary_grades,id'],
            'headcount' => ['required_without:job_requisition_id', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'requirements' => ['required', 'string', 'min:3'],
            'location' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['sometimes', 'string', Rule::in(EmploymentType::values())],
            'is_internal' => ['sometimes', 'boolean'],
            'is_external' => ['sometimes', 'boolean'],
            'closes_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
