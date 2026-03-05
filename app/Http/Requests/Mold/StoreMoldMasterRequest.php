<?php

declare(strict_types=1);

namespace App\Http\Requests\Mold;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMoldMasterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:200',
            'description'  => 'nullable|string',
            'cavity_count' => 'required|integer|min:1',
            'material'     => 'nullable|string|max:100',
            'location'     => 'nullable|string|max:200',
            'max_shots'    => 'nullable|integer|min:1',
            'status'       => 'in:active,under_maintenance,retired',
            'is_active'    => 'boolean',
        ];
    }
}
