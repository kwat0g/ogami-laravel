<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMaintenanceWorkOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'equipment_id'   => 'required|exists:equipment,id',
            'type'           => 'required|in:corrective,preventive',
            'priority'       => 'required|in:low,normal,high,critical',
            'title'          => 'required|string|max:300',
            'description'    => 'nullable|string',
            'assigned_to_id' => 'nullable|exists:users,id',
            'scheduled_date' => 'nullable|date',
        ];
    }
}
