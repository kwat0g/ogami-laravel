<?php

declare(strict_types=1);

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

final class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy checked in controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'urgency' => ['sometimes', 'string', 'in:normal,urgent,critical'],
            'justification' => ['required', 'string', 'min:5'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.vendor_item_id' => ['required', 'integer', 'distinct', 'exists:vendor_items,id'],
            'items.*.item_description' => ['required', 'string', 'max:255'],
            'items.*.unit_of_measure' => ['required', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.estimated_unit_cost' => ['required', 'numeric', 'gt:0'],
            'items.*.specifications' => ['nullable', 'string'],
        ];
    }
}
