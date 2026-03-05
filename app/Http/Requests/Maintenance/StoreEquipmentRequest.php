<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

final class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name'             => 'required|string|max:200',
            'category'         => 'nullable|string|max:100',
            'manufacturer'     => 'nullable|string|max:200',
            'model_number'     => 'nullable|string|max:100',
            'serial_number'    => 'nullable|string|max:100',
            'location'         => 'nullable|string|max:200',
            'commissioned_on'  => 'nullable|date',
            'status'           => 'in:operational,under_maintenance,decommissioned',
            'is_active'        => 'boolean',
        ];
    }
}
