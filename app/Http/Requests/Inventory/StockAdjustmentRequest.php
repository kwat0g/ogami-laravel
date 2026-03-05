<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

final class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'item_id'      => ['required', 'exists:item_masters,id'],
            'location_id'  => ['required', 'exists:warehouse_locations,id'],
            'adjusted_qty' => ['required', 'numeric', 'min:0'],
            'remarks'      => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
