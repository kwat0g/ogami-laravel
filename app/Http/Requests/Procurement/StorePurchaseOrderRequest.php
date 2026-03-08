<?php

declare(strict_types=1);

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

final class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'purchase_request_id'            => ['required', 'integer', 'exists:purchase_requests,id'],
            'vendor_id'                       => ['required', 'integer', 'exists:vendors,id'],
            'delivery_date'                   => ['required', 'date', 'after_or_equal:today'],
            'payment_terms'                   => ['required', 'string', 'max:50'],
            'delivery_address'               => ['nullable', 'string'],
            'notes'                           => ['nullable', 'string'],

            'items'                           => ['required', 'array', 'min:1'],
            'items.*.pr_item_id'              => ['nullable', 'integer', 'exists:purchase_request_items,id'],
            'items.*.item_master_id'          => ['required', 'integer', 'exists:item_masters,id'],
            'items.*.item_description'        => ['required', 'string', 'max:255'],
            'items.*.unit_of_measure'         => ['required', 'string', 'max:30'],
            'items.*.quantity_ordered'        => ['required', 'numeric', 'gt:0'],
            'items.*.agreed_unit_cost'        => ['required', 'numeric', 'gt:0'],
        ];
    }
}
