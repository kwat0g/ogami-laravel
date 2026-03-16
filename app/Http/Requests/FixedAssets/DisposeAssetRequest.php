<?php

declare(strict_types=1);

namespace App\Http\Requests\FixedAssets;

use Illuminate\Foundation\Http\FormRequest;

final class DisposeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    public function rules(): array
    {
        return [
            'disposal_date' => ['required', 'date'],
            'proceeds_centavos' => ['nullable', 'integer', 'min:0'],
            'disposal_method' => ['nullable', 'string', 'in:sale,scrap,donation,write_off'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
