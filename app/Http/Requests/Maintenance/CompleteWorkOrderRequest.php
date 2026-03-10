<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

final class CompleteWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    public function rules(): array
    {
        return [
            'completion_notes'       => ['required', 'string'],
            'labor_hours'            => ['nullable', 'numeric', 'min:0'],
            'actual_completion_date' => ['nullable', 'date'],
        ];
    }
}
