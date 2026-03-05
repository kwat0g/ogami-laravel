<?php

declare(strict_types=1);

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'department_id'                   => ['sometimes', 'integer', 'exists:departments,id'],
            'urgency'                          => ['sometimes', 'string', 'in:normal,urgent,critical'],
            'justification'                    => ['sometimes', 'string', 'min:20'],
            'notes'                            => ['nullable', 'string'],

            'items'                            => ['sometimes', 'array', 'min:1'],
            'items.*.item_description'         => ['required_with:items', 'string', 'max:255'],
            'items.*.unit_of_measure'          => ['required_with:items', 'string', 'max:30'],
            'items.*.quantity'                 => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.estimated_unit_cost'      => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.specifications'           => ['nullable', 'string'],
        ];
    }
}
