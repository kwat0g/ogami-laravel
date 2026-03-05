<?php

declare(strict_types=1);

namespace App\Http\Requests\QC;

use Illuminate\Foundation\Http\FormRequest;

final class RecordInspectionResultsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'qty_passed'                            => 'required|numeric|min:0',
            'qty_failed'                            => 'required|numeric|min:0',
            'results'                               => 'required|array|min:1',
            'results.*.inspection_template_item_id' => 'nullable|exists:inspection_template_items,id',
            'results.*.criterion'                   => 'required|string|max:300',
            'results.*.actual_value'                => 'nullable|string|max:200',
            'results.*.is_conforming'               => 'nullable|boolean',
            'results.*.remarks'                     => 'nullable|string',
        ];
    }
}
