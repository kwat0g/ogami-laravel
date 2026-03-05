<?php

declare(strict_types=1);

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller calls $this->authorize() via policy
    }

    public function rules(): array
    {
        return [
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
            'first_deduction_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_deduction_date.required' => 'A first deduction date is required to approve a loan.',
            'first_deduction_date.after_or_equal' => 'First deduction date cannot be in the past.',
        ];
    }
}
