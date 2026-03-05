<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates General Ledger report filters.
 *
 * GL-001: requires account_id + date range. cost_center_id is optional.
 */
class GeneralLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'cost_center_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'An account must be selected.',
            'account_id.exists' => 'The selected account does not exist.',
            'date_from.required' => 'Start date is required.',
            'date_to.required' => 'End date is required.',
            'date_to.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
