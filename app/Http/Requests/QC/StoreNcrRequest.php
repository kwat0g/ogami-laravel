<?php

declare(strict_types=1);

namespace App\Http\Requests\QC;

use Illuminate\Foundation\Http\FormRequest;

final class StoreNcrRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'inspection_id' => 'required|exists:inspections,id',
            'title'         => 'required|string|max:300',
            'description'   => 'required|string',
            'severity'      => 'required|in:minor,major,critical',
        ];
    }
}
