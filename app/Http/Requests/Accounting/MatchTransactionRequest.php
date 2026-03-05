<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a match-transaction request — links a bank transaction to
 * a specific GL journal entry line.
 */
class MatchTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_transaction_id' => ['required', 'integer', 'exists:bank_transactions,id'],
            'journal_entry_line_id' => ['required', 'integer', 'exists:journal_entry_lines,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_transaction_id.exists' => 'The specified bank transaction does not exist.',
            'journal_entry_line_id.exists' => 'The specified journal entry line does not exist.',
        ];
    }
}
