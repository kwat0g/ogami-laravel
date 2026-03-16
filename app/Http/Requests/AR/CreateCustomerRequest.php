<?php

declare(strict_types=1);

namespace App\Http\Requests\AR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates customer create / update payloads.
 */
class CreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate in controller
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')?->id;

        return [
            'name' => ['required', 'string', 'max:200'],
            // MED-002: TIN format and uniqueness validation
            'tin' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value) {
                        return;
                    }
                    // Validate format: XXX-XXX-XXX-XXX (12 digits + 3 dashes)
                    $normalized = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($normalized) !== 12) {
                        $fail('The TIN must be 12 digits in format XXX-XXX-XXX-XXX.');
                    }
                },
                Rule::unique('customers', 'tin')->ignore($customerId),
            ],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'contact_person' => ['nullable', 'string', 'max:200'],
            'address' => ['nullable', 'string'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'ar_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
