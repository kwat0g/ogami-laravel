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
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
