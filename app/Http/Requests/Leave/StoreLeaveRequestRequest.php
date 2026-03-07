<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id'     => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id'   => ['required', 'integer', 'exists:leave_types,id,is_active,1'],
            'date_from'       => ['required', 'date'],
            'date_to'         => ['required', 'date', 'after_or_equal:date_from'],
            'total_days'      => ['sometimes', 'nullable', 'numeric', 'min:0.5', 'max:365'],
            'is_half_day'     => ['sometimes', 'boolean'],
            'half_day_period' => ['required_if:is_half_day,true', Rule::in(['am', 'pm', 'AM', 'PM'])],
            'reason'          => ['required', 'string', 'max:500'],
        ];
    }
}
