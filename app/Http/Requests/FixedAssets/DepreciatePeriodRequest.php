<?php

declare(strict_types=1);

namespace App\Http\Requests\FixedAssets;

use Illuminate\Foundation\Http\FormRequest;

final class DepreciatePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    public function rules(): array
    {
        return [
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
        ];
    }
}
