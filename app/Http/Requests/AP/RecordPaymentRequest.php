<?php

declare(strict_types=1);

namespace App\Http\Requests\AP;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in VendorInvoiceController
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // AP-008: amount must be positive
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_method' => [
                'nullable',
                Rule::in(['bank_transfer', 'check', 'cash']),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.min' => 'Payment amount must be greater than zero. (AP-008)',
        ];
    }
}
