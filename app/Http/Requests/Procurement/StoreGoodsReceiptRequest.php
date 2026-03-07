<?php

declare(strict_types=1);

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

final class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'purchase_order_id'              => ['required', 'integer', 'exists:purchase_orders,id'],
            'received_date'                  => ['nullable', 'date'],
            'delivery_note_number'           => ['nullable', 'string', 'max:100'],
            'condition_notes'               => ['nullable', 'string'],

            'items'                          => ['required', 'array', 'min:1'],
            'items.*.po_item_id'             => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity_received'      => ['required', 'numeric', 'gt:0'],
            'items.*.unit_of_measure'        => ['required', 'string', 'max:30'],
            'items.*.condition'              => ['sometimes', 'string', 'in:good,damaged,partial,rejected'],
            'items.*.remarks'               => ['nullable', 'string'],
        ];
    }
}
