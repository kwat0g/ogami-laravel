<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDeliveryScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'product_item_id' => ['required', 'integer', 'exists:item_masters,id'],
            'qty_ordered' => ['required', 'numeric', 'min:0.0001'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'target_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'type' => ['sometimes', 'string', 'in:local,export'],
            'status' => ['sometimes', 'string', 'in:open,in_production,ready,dispatched,delivered,cancelled'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
