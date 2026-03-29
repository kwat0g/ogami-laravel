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
        return $this->user()->can('recruitment.postings.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'job_requisition_id' => ['required', 'integer', 'exists:job_requisitions,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:50'],
            'requirements' => ['required', 'string', 'min:20'],
            'location' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['sometimes', 'string', Rule::in(EmploymentType::values())],
            'is_internal' => ['sometimes', 'boolean'],
            'is_external' => ['sometimes', 'boolean'],
            'closes_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
