<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

final class AddMaintenancePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', 'exists:item_masters,id'],
            'location_id' => ['required', 'integer', 'exists:warehouse_locations,id'],
            'qty_required' => ['required', 'numeric', 'min:0.0001'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
