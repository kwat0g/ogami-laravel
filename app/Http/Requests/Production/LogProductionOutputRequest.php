<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

final class LogProductionOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'shift'        => ['required', 'string', 'in:A,B,C'],
            'log_date'     => ['required', 'date'],
            'qty_produced' => ['required', 'numeric', 'min:0.0001'],
            'qty_rejected' => ['sometimes', 'numeric', 'min:0'],
            'operator_id'  => ['required', 'integer', 'exists:employees,id'],
            'remarks'      => ['nullable', 'string', 'max:500'],
        ];
    }
}
