<?php

declare(strict_types=1);

namespace App\Http\Requests\AP;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateVendorInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in VendorInvoiceController
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'ap_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'expense_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'invoice_date' => ['required', 'date'],
            // AP-001: due_date must be on or after invoice_date
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'net_amount' => ['required', 'numeric', 'min:0.01'],
            'vat_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            // AP-003: OR number required when VAT > 0
            'or_number' => [
                'nullable',
                'string',
                'max:30',
                Rule::requiredIf(fn () => (float) ($this->input('vat_amount', 0)) > 0),
            ],
            'vat_exemption_reason' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'or_number.required' => 'Official receipt number is required when VAT amount is greater than zero. (AP-003)',
            'due_date.after_or_equal' => 'Due date must be on or after the invoice date. (AP-001)',
        ];
    }
}
