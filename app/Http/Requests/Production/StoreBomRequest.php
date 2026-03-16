<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'product_item_id' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'exists:item_masters,id'],
            'version' => ['sometimes', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.component_item_id' => ['required', 'integer', 'exists:item_masters,id'],
            'components.*.qty_per_unit' => ['required', 'numeric', 'min:0.0001'],
            'components.*.unit_of_measure' => ['required', 'string', 'max:20'],
            'components.*.scrap_factor_pct' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
