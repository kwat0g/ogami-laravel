<?php

declare(strict_types=1);

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'loan_type_id' => ['required', 'integer', 'exists:loan_types,id,is_active,1'],
            'principal_centavos' => ['required', 'integer', 'min:1'],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'deduction_cutoff' => ['required', 'string', 'in:1st,2nd'],
            'purpose' => ['sometimes', 'nullable', 'string', 'max:500'],
            'first_deduction_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
