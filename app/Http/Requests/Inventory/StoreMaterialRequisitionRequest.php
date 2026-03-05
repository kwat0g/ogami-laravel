<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMaterialRequisitionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'department_id'       => ['required', 'exists:departments,id'],
            'purpose'             => ['required', 'string', 'min:10', 'max:2000'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.item_id'     => ['required', 'exists:item_masters,id'],
            'items.*.qty_requested' => ['required', 'numeric', 'min:0.0001'],
            'items.*.remarks'     => ['nullable', 'string', 'max:500'],
        ];
    }
}
