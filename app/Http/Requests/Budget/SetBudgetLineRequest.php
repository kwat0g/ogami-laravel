<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use Illuminate\Foundation\Http\FormRequest;

final class SetBudgetLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    public function rules(): array
    {
        return [
            'cost_center_id'           => ['required', 'integer', 'exists:cost_centers,id'],
            'fiscal_year'              => ['required', 'integer', 'min:2000', 'max:2100'],
            'account_id'               => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'budgeted_amount_centavos' => ['required', 'integer', 'min:0'],
            'notes'                    => ['nullable', 'string'],
        ];
    }
}
