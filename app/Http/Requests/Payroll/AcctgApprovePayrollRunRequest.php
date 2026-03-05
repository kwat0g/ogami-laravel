<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request for Step 7: Accounting Manager final approval or rejection.
 */
final class AcctgApprovePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:APPROVED,REJECTED'],
            // Required if rejecting
            'rejection_reason' => ['required_if:action,REJECTED', 'nullable', 'string', 'min:10', 'max:5000'],
            // Required 3-checkbox confirmation when approving
            'checkboxes_checked' => ['required_if:action,APPROVED', 'nullable', 'array', 'min:3'],
            'checkboxes_checked.*' => ['string'],
            'comments' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
