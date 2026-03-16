<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use App\Domains\HR\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
        /** @var Employee $employee */
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

            // Government IDs — unique ignoring this employee.
            // MED-002: Added format validation for Philippine government IDs.
            // Closure validators normalize the input (strip dashes/spaces, uppercase) and
            // hash it before checking the hash column, so "12-3456789-0" and "1234567890"
            // are treated as the same ID and correctly detected as duplicates.
            'sss_no' => ['sometimes', 'nullable', 'string', 'max:12', 'regex:/^\d{2}-\d{7}-\d$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($employeeId): void {
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
                    if (DB::table('employees')->where('sss_no_hash', $hash)->where('id', '!=', $employeeId)->exists()) {
                        $fail('This SSS number is already registered to another employee.');
                    }
                },
            ],
            'tin' => ['sometimes', 'nullable', 'string', 'max:15', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($employeeId): void {
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
                    if (DB::table('employees')->where('tin_hash', $hash)->where('id', '!=', $employeeId)->exists()) {
                        $fail('This TIN is already registered to another employee.');
                    }
                },
            ],
            'philhealth_no' => ['sometimes', 'nullable', 'string', 'max:14', 'regex:/^\d{2}-\d{9}-\d$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($employeeId): void {
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
                    if (DB::table('employees')->where('philhealth_no_hash', $hash)->where('id', '!=', $employeeId)->exists()) {
                        $fail('This PhilHealth number is already registered to another employee.');
                    }
                },
            ],
            'pagibig_no' => ['sometimes', 'nullable', 'string', 'max:14', 'regex:/^\d{4}-\d{4}-\d{4}$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($employeeId): void {
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
                    if (DB::table('employees')->where('pagibig_no_hash', $hash)->where('id', '!=', $employeeId)->exists()) {
                        $fail('This Pag-IBIG number is already registered to another employee.');
                    }
                },
            ],

            // Bank
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_no' => ['sometimes', 'nullable', 'string', 'max:30'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
