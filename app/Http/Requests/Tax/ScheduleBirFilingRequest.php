<?php

declare(strict_types=1);

namespace App\Http\Requests\Tax;

use App\Domains\Tax\Services\BirFilingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ScheduleBirFilingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    public function rules(): array
    {
        return [
            'form_type' => ['required', Rule::in(BirFilingService::FORM_TYPES)],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'due_date' => ['nullable', 'date'],
            'total_tax_due_centavos' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
