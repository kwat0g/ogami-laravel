<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

final class StoreItemMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:item_categories,id'],
            'name' => ['required', 'string', 'max:200'],
            'unit_of_measure' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'reorder_qty' => ['nullable', 'numeric', 'min:0'],
            'type' => ['required', 'in:raw_material,semi_finished,finished_good,consumable,spare_part'],
            'requires_iqc' => ['nullable', 'boolean'],
        ];
    }
}
