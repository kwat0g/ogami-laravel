<?php

declare(strict_types=1);

namespace App\Http\Requests\AR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_method' => [
                'nullable',
                Rule::in(['bank_transfer', 'check', 'cash', 'online']),
            ],
            'notes' => ['nullable', 'string'],
            // GL accounts for JE: DR Cash / CR AR
            'cash_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'ar_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
        ];
    }
}
