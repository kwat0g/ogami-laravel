<?php

declare(strict_types=1);

namespace App\Http\Requests\HR\Recruitment;

use App\Domains\HR\Recruitment\Enums\EvaluationRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubmitEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruitment.interviews.evaluate');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scorecard' => ['required', 'array', 'min:1'],
            'scorecard.*.criterion' => ['required', 'string', 'max:100'],
            'scorecard.*.score' => ['required', 'integer', 'min:1', 'max:5'],
            'scorecard.*.comments' => ['nullable', 'string', 'max:500'],
            'recommendation' => ['required', 'string', Rule::in(EvaluationRecommendation::values())],
            'general_remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
