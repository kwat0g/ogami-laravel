<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use Illuminate\Foundation\Http\FormRequest;

final class ListBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    public function rules(): array
    {
        return [
            'cost_center_id' => ['required', 'integer', 'exists:cost_centers,id'],
            'fiscal_year'    => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}
