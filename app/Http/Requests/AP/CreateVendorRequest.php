<?php

declare(strict_types=1);

namespace App\Http\Requests\AP;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in VendorController
    }

    /**
     * Prepare the data for validation.
     * Convert empty strings to null for optional fields.
     */
    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'tin', 'atc_code', 'address', 'contact_person', 'email', 'phone',
            'notes', 'payment_terms', 'bank_name', 'bank_account_no',
            'bank_account_name', 'accreditation_status', 'accreditation_notes',
        ];

        $normalized = [];
        foreach ($nullableFields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $normalized[$field] = null;
            }
        }

        if (! empty($normalized)) {
            $this->merge($normalized);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $vendorId = $this->route('vendor')?->id; // null on create, set on update

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
                Rule::unique('vendors', 'tin')->ignore($vendorId)->whereNull('deleted_at'),
            ],
            'ewt_rate_id' => ['nullable', 'integer', 'exists:ewt_rates,id'],
            'atc_code' => ['nullable', 'string', 'max:10'],
            'is_ewt_subject' => ['required', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_terms' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:200'],
            'bank_account_no' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:200'],
            'accreditation_status' => ['nullable', 'string', 'max:50'],
            'accreditation_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
