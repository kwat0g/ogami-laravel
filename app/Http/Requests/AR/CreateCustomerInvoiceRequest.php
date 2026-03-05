<?php

declare(strict_types=1);

namespace App\Http\Requests\AR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

/**
 * Validates customer invoice create / update payloads.
 *
 * VAT-001: or_number required when vat_amount > 0.
 * VAT-003: vat_exemption_reason must be from system_settings list when vat_amount = 0 and reason provided.
 */
class CreateCustomerInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'ar_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'revenue_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'invoice_date' => ['required', 'date'],
            // AR-001: due_date >= invoice_date
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'subtotal' => ['required', 'numeric', 'min:0.01'],
            // VAT-002: computed from subtotal × vat_rate; nullable means VAT-exempt
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            // VAT-001: OR number required when VAT is charged
            'or_number' => ['nullable', 'string', 'max:50', 'required_if_has_vat'],
            // VAT-003: exemption reason must be from allowed list
            'vat_exemption_reason' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
            // AR-002: bypass credit check only with explicit permission
            'bypass_credit_check' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function ($v) {
            $vatAmount = (float) ($this->input('vat_amount') ?? 0);

            // VAT-001: OR number required when VAT > 0
            if ($vatAmount > 0 && empty($this->input('or_number'))) {
                $v->errors()->add('or_number', 'Official receipt number is required when VAT is greater than zero. (VAT-001)');
            }

            // VAT-003: exemption reason must appear in allowed list
            $reason = $this->input('vat_exemption_reason');
            if (! empty($reason)) {
                $allowed = $this->resolveVatExemptionReasons();
                if (! empty($allowed) && ! in_array($reason, $allowed, true)) {
                    $v->errors()->add('vat_exemption_reason', 'The VAT exemption reason is not from the approved list. (VAT-003)');
                }
            }
        });
    }

    private function resolveVatExemptionReasons(): array
    {
        $raw = DB::table('system_settings')->where('key', 'tax.vat_exemption_reasons')->value('value');

        return $raw ? (array) json_decode($raw, true) : [];
    }
}
