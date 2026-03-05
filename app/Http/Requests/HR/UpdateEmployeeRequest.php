<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the update-employee payload.
 * All fields are optional (PATCH semantics).
 *
 * @property-read string|null $first_name
 * @property-read string|null $last_name
 * @property-read int|null    $basic_monthly_rate
 */
class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Domains\HR\Models\Employee $employee */
        $employee = $this->route('employee');

        return $this->user()?->can('update', $employee) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $employeeId = $this->route('employee')->id ?? 0;

        return [
            // Personal
            'first_name' => ['sometimes', 'string', 'max:80'],
            'last_name' => ['sometimes', 'string', 'max:80'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'suffix' => ['sometimes', 'nullable', 'string', 'max:10'],
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
            'gender' => ['sometimes', Rule::in(['male', 'female', 'other'])],
            'civil_status' => ['sometimes', 'nullable', 'string', 'max:20'],
            'citizenship' => ['sometimes', 'nullable', 'string', 'max:60'],
            'present_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'permanent_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'personal_email' => ['sometimes', 'nullable', 'email:rfc', 'max:150'],
            'personal_phone' => ['sometimes', 'nullable', 'string', 'max:20'],

            // Organizational
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'position_id' => ['sometimes', 'nullable', 'integer', 'exists:positions,id'],
            'salary_grade_id' => ['sometimes', 'nullable', 'integer', 'exists:salary_grades,id'],
            'reports_to' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],

            // Employment
            'employment_type' => ['sometimes', Rule::in([
                'regular', 'contractual', 'project_based', 'casual', 'probationary',
            ])],
            'pay_basis' => ['sometimes', Rule::in(['monthly', 'daily'])],
            'basic_monthly_rate' => ['sometimes', 'integer', 'min:1'],
            'date_hired' => ['sometimes', 'date'],
            'regularization_date' => ['sometimes', 'nullable', 'date'],
            'separation_date' => ['sometimes', 'nullable', 'date'],

            // Government IDs — unique ignoring this employee
            'sss_no' => ['sometimes', 'nullable', 'string', 'max:12',
                Rule::unique('employees', 'sss_no_hash')->ignore($employeeId)],
            'tin' => ['sometimes', 'nullable', 'string', 'max:15',
                Rule::unique('employees', 'tin_hash')->ignore($employeeId)],
            'philhealth_no' => ['sometimes', 'nullable', 'string', 'max:14',
                Rule::unique('employees', 'philhealth_no_hash')->ignore($employeeId)],
            'pagibig_no' => ['sometimes', 'nullable', 'string', 'max:14',
                Rule::unique('employees', 'pagibig_no_hash')->ignore($employeeId)],

            // Bank
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_no' => ['sometimes', 'nullable', 'string', 'max:30'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
