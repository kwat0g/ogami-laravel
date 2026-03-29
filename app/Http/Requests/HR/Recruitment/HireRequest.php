<?php

declare(strict_types=1);

namespace App\Http\Requests\HR\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

final class HireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruitment.hiring.execute');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'string', 'in:male,female,other'],
            'civil_status' => ['sometimes', 'string', 'in:SINGLE,MARRIED,WIDOWED,SEPARATED'],
            'bir_status' => ['sometimes', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
