<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared financial statement request — used by:
 *   - Trial Balance    (date_from + date_to)
 *   - Income Statement (date_from + date_to)
 *   - Cash Flow        (date_from + date_to)
 *   - Balance Sheet    (as_of_date; optionally comparative_date)
 *
 * The controller is responsible for routing to the correct service method
 * based on which parameters are provided.
 */
class FinancialStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // For period-based reports (Trial Balance, Income Statement, Cash Flow)
            'date_from' => ['nullable', 'date', 'required_without:as_of_date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from', 'required_without:as_of_date'],
            // For point-in-time reports (Balance Sheet)
            'as_of_date' => ['nullable', 'date', 'required_without:date_from'],
            'comparative_date' => ['nullable', 'date', 'before:as_of_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.required_without' => 'Start date is required when not using as-of-date.',
            'date_to.required_without' => 'End date is required when not using as-of-date.',
            'as_of_date.required_without' => 'As-of date is required when not using a date range.',
            'date_to.after_or_equal' => 'End date must be on or after the start date.',
            'comparative_date.before' => 'Comparative date must be before the as-of date.',
        ];
    }
}
