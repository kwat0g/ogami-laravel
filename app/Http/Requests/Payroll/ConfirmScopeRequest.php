<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request for Step 2: Confirm Employee Scope.
 */
final class ConfirmScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy check done in controller
    }

    public function rules(): array
    {
        return [
            'departments' => ['nullable', 'array'],
            'departments.*' => ['integer', 'exists:departments,id'],
            'positions' => ['nullable', 'array'],
            'positions.*' => ['integer', 'exists:positions,id'],
            'employment_types' => ['nullable', 'array'],
            'employment_types.*' => ['string', 'in:regular,contractual,project_based,casual,probationary'],
            'include_unpaid_leave' => ['boolean'],
            'include_probation_end' => ['boolean'],
            'exclude_no_attendance' => ['boolean'],
            'exclusions' => ['nullable', 'array'],
            'exclusions.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'exclusions.*.reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
