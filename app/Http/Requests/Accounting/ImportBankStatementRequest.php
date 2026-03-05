<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates bulk bank statement import payload.
 *
 * Expects an array of transaction lines — not a file upload.
 * Each line must have: transaction_date, description, amount > 0,
 * transaction_type (debit|credit), and optionally reference_number.
 */
class ImportBankStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*.transaction_date' => ['required', 'date'],
            'transactions.*.description' => ['required', 'string', 'max:500'],
            'transactions.*.amount' => ['required', 'numeric', 'gt:0'],
            'transactions.*.transaction_type' => ['required', 'string', 'in:debit,credit'],
            'transactions.*.reference_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'transactions.required' => 'At least one transaction must be provided.',
            'transactions.min' => 'At least one transaction must be provided.',
            'transactions.*.transaction_date.required' => 'Each transaction must have a date.',
            'transactions.*.amount.gt' => 'Transaction amount must be greater than zero.',
            'transactions.*.transaction_type.in' => 'Transaction type must be debit or credit.',
        ];
    }
}
