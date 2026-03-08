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
            'tin' => [
                'nullable',
                'string',
                'max:20',
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
