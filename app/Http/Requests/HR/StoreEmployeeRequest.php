<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the create-employee payload.
 * Government IDs arrive as plain strings and are encrypted in EmployeeService.
 *
 * @property-read string $first_name
 * @property-read string $last_name
 * @property-read string|null $middle_name
 * @property-read string $date_of_birth
 * @property-read string $gender
 * @property-read string $employment_type
 * @property-read string $pay_basis
 * @property-read int    $basic_monthly_rate   centavos
 * @property-read string $date_hired
 * @property-read int|null $salary_grade_id
 * @property-read int|null $department_id
 * @property-read string|null $sss_no
 * @property-read string|null $tin
 * @property-read string|null $philhealth_no
 * @property-read string|null $pagibig_no
 */
class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Domains\HR\Models\Employee::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Optional caller-supplied code; auto-generated if omitted
            'employee_code' => ['nullable', 'string', 'max:30', 'unique:employees,employee_code'],

            // Personal
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'civil_status' => ['nullable', 'string', 'max:20'],
            'citizenship' => ['nullable', 'string', 'max:60'],
            'present_address' => ['nullable', 'string', 'max:255'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'personal_email' => ['nullable', 'email:rfc', 'max:150'],
            'personal_phone' => ['nullable', 'string', 'max:20'],

            // Organizational
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'salary_grade_id' => ['nullable', 'integer', 'exists:salary_grades,id'],
            'reports_to' => ['nullable', 'integer', 'exists:employees,id'],

            // Employment
            'employment_type' => ['required', Rule::in([
                'regular', 'contractual', 'project_based', 'casual', 'probationary',
            ])],
            'pay_basis' => ['required', Rule::in(['monthly', 'daily'])],
            'basic_monthly_rate' => ['required', 'integer', 'min:1'],
            'date_hired' => ['required', 'date'],

            // Government IDs (optional on create; encrypted by service)
            'sss_no' => ['nullable', 'string', 'max:12', 'unique:employees,sss_no_hash'],
            'tin' => ['nullable', 'string', 'max:15', 'unique:employees,tin_hash'],
            'philhealth_no' => ['nullable', 'string', 'max:14', 'unique:employees,philhealth_no_hash'],
            'pagibig_no' => ['nullable', 'string', 'max:14', 'unique:employees,pagibig_no_hash'],

            // Bank
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_no' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
