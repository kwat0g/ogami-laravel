<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates bank account create / update payloads.
 */
class CreateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bankAccountId = $this->route('bankAccount')?->id;

        return [
            'name' => ['required', 'string', 'max:200'],
            'account_number' => [
                'required',
                'string',
                'max:50',
                'unique:bank_accounts,account_number'.($bankAccountId ? ",{$bankAccountId}" : ''),
            ],
            'bank_name' => ['required', 'string', 'max:200'],
            'account_type' => ['required', 'string', 'in:checking,savings'],
            'account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_number.unique' => 'This bank account number already exists.',
            'account_type.in' => 'Account type must be checking or savings.',
        ];
    }
}
