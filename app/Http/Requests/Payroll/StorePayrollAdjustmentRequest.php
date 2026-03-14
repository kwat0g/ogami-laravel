<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.initiate') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'nature' => ['required', Rule::in(['taxable', 'non_taxable'])],
            'description' => ['required', 'string', 'max:255'],
            'amount_centavos' => ['required', 'integer', 'min:1'],
        ];
    }
}
