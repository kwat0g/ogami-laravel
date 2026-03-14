<?php

declare(strict_types=1);

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'vendor_id'                       => ['sometimes', 'integer', 'exists:vendors,id'],
            'delivery_date'                   => ['sometimes', 'date', 'after_or_equal:today'],
            'payment_terms'                   => ['sometimes', 'string', 'max:50'],
            'delivery_address'               => ['nullable', 'string'],
            'notes'                           => ['nullable', 'string'],

            'items'                           => ['sometimes', 'array', 'min:1'],
            'items.*.pr_item_id'              => ['nullable', 'integer', 'exists:purchase_request_items,id'],
            'items.*.item_master_id'          => ['nullable', 'integer', 'exists:item_masters,id'],
            'items.*.item_description'        => ['required_with:items', 'string', 'max:255'],
            'items.*.unit_of_measure'         => ['required_with:items', 'string', 'max:30'],
            'items.*.quantity_ordered'        => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.agreed_unit_cost'        => ['required_with:items', 'numeric', 'gt:0'],
        ];
    }
}
