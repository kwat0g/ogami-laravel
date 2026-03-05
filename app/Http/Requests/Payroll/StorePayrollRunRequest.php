<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

final class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.initiate') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'run_type' => ['sometimes', 'in:regular,thirteenth_month,adjustment,year_end_reconciliation,final_pay'],
            'pay_period_id' => ['nullable', 'integer', 'exists:pay_periods,id'],
            'cutoff_start' => ['required', 'date'],
            'cutoff_end' => ['required', 'date', 'after_or_equal:cutoff_start'],
            'pay_date' => ['required', 'date', 'after_or_equal:cutoff_end'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
