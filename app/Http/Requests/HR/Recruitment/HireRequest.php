<?php

declare(strict_types=1);

namespace App\Http\Requests\HR\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

final class HireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruitment.hiring.execute');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'string', 'in:male,female,other'],
            'civil_status' => ['sometimes', 'string', 'in:SINGLE,MARRIED,WIDOWED,SEPARATED'],
            'citizenship' => ['nullable', 'string', 'max:50'],
            'present_address' => ['required', 'string'],
            'permanent_address' => ['nullable', 'string'],
            'personal_email' => ['required', 'email', 'max:255'],
            'personal_phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'salary_grade_id' => ['nullable', 'integer', 'exists:salary_grades,id'],
            'reports_to' => ['nullable', 'integer', 'exists:employees,id'],
            'employment_type' => ['required', 'string', 'in:regular,contractual,project_based,seasonal,probationary'],
            'pay_basis' => ['required', 'string', 'in:monthly,daily,hourly'],
            'basic_monthly_rate' => ['required', 'integer', 'min:1'],
            'regularization_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'bir_status' => ['sometimes', 'string'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_no' => ['nullable', 'string', 'max:60'],
            'sss_no' => ['nullable', 'string', 'max:40'],
            'tin' => ['nullable', 'string', 'max:40'],
            'philhealth_no' => ['nullable', 'string', 'max:40'],
            'pagibig_no' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
