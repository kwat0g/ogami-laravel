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
        $user = $this->user();

        $inHrDepartment = $user !== null
            && (
                $user->departments()->where('departments.code', 'HR')->exists()
                || $user->primaryDepartment?->code === 'HR'
                || $user->employee?->department?->code === 'HR'
            );

        return $user !== null
            && (
                $user->can('recruitment.interviews.evaluate')
                || $user->can('recruitment.hiring.execute')
                || $user->can('hr.full_access')
                || (($user->hasRole('manager') || $user->hasRole('officer') || $user->hasRole('head')) && $inHrDepartment)
            );
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
