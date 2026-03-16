<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use App\Domains\HR\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
        return $this->user()?->can('create', Employee::class) ?? false;
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

            // Government IDs (optional on create; encrypted by service).
            // MED-002: Added format validation for Philippine government IDs.
            // Closure validators normalize the input (strip dashes/spaces, uppercase) and
            // hash it before checking the hash column, so "12-3456789-0" and "1234567890"
            // are treated as the same ID and correctly detected as duplicates.
            'sss_no' => ['nullable', 'string', 'max:12', 'regex:/^\d{2}-\d{7}-\d$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value) {
                        return;
                    }
                    // Validate format: XX-XXXXXXX-X (10 digits + 2 dashes)
                    $normalized = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($normalized) !== 10) {
                        $fail('The SSS number must be 10 digits in format XX-XXXXXXX-X.');

                        return;
                    }
                    $hash = hash('sha256', strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $value)));
                    if (DB::table('employees')->where('sss_no_hash', $hash)->exists()) {
                        $fail('This SSS number is already registered to another employee.');
                    }
                },
            ],
            'tin' => ['nullable', 'string', 'max:15', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value) {
                        return;
                    }
                    // Validate format: XXX-XXX-XXX-XXX (12 digits + 3 dashes)
                    $normalized = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($normalized) !== 12) {
                        $fail('The TIN must be 12 digits in format XXX-XXX-XXX-XXX.');

                        return;
                    }
                    $hash = hash('sha256', strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $value)));
                    if (DB::table('employees')->where('tin_hash', $hash)->exists()) {
                        $fail('This TIN is already registered to another employee.');
                    }
                },
            ],
            'philhealth_no' => ['nullable', 'string', 'max:14', 'regex:/^\d{2}-\d{9}-\d$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value) {
                        return;
                    }
                    // Validate format: XX-XXXXXXXXX-X (12 digits + 2 dashes)
                    $normalized = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($normalized) !== 12) {
                        $fail('The PhilHealth number must be 12 digits in format XX-XXXXXXXXX-X.');

                        return;
                    }
                    $hash = hash('sha256', strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $value)));
                    if (DB::table('employees')->where('philhealth_no_hash', $hash)->exists()) {
                        $fail('This PhilHealth number is already registered to another employee.');
                    }
                },
            ],
            'pagibig_no' => ['nullable', 'string', 'max:14', 'regex:/^\d{4}-\d{4}-\d{4}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value) {
                        return;
                    }
                    // Validate format: XXXX-XXXX-XXXX (12 digits + 2 dashes)
                    $normalized = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($normalized) !== 12) {
                        $fail('The Pag-IBIG number must be 12 digits in format XXXX-XXXX-XXXX.');

                        return;
                    }
                    $hash = hash('sha256', strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $value)));
                    if (DB::table('employees')->where('pagibig_no_hash', $hash)->exists()) {
                        $fail('This Pag-IBIG number is already registered to another employee.');
                    }
                },
            ],

            // Bank
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_no' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
