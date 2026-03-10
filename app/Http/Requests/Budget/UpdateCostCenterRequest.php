<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    public function rules(): array
    {
        /** @var \App\Domains\Budget\Models\CostCenter $costCenter */
        $costCenter = $this->route('costCenter');

        return [
            'name'          => ['sometimes', 'string', 'max:120'],
            'code'          => ['sometimes', 'string', 'max:30', 'unique:cost_centers,code,' . $costCenter->id],
            'description'   => ['nullable', 'string'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'parent_id'     => ['nullable', 'integer', 'exists:cost_centers,id'],
            'is_active'     => ['sometimes', 'boolean'],
        ];
    }
}
