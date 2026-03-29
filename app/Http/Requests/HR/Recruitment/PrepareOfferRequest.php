<?php

declare(strict_types=1);

namespace App\Http\Requests\HR\Recruitment;

use App\Domains\HR\Recruitment\Enums\EmploymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PrepareOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruitment.offers.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'offered_position_id' => ['required', 'integer', 'exists:positions,id'],
            'offered_department_id' => ['required', 'integer', 'exists:departments,id'],
            'offered_salary' => ['required', 'integer', 'min:1'],
            'employment_type' => ['required', 'string', Rule::in(EmploymentType::values())],
            'start_date' => ['required', 'date', 'after:today'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'offer_letter_path' => ['nullable', 'string', 'max:500'],
        ];
    }
}
