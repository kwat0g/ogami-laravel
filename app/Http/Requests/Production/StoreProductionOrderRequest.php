<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'delivery_schedule_id' => ['nullable', 'integer', 'exists:delivery_schedules,id'],
            'product_item_id' => ['required', 'integer', 'exists:item_masters,id'],
            'bom_id' => ['required', 'integer', 'exists:bill_of_materials,id'],
            'qty_required' => ['required', 'numeric', 'min:0.0001'],
            'target_start_date' => ['required', 'date'],
            'target_end_date' => ['required', 'date', 'after_or_equal:target_start_date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
