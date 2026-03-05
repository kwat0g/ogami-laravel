<?php

declare(strict_types=1);

namespace App\Http\Requests\QC;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInspectionTemplateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name'                       => 'required|string|max:200',
            'stage'                      => 'required|in:iqc,ipqc,oqc',
            'description'                => 'nullable|string',
            'is_active'                  => 'boolean',
            'items'                      => 'array|min:1',
            'items.*.criterion'          => 'required|string|max:300',
            'items.*.method'             => 'nullable|string|max:200',
            'items.*.acceptable_range'   => 'nullable|string|max:200',
            'items.*.sort_order'         => 'nullable|integer|min:0',
        ];
    }
}
