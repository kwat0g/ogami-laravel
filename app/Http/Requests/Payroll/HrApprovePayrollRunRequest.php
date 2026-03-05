<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request for Step 6: HR Manager approval or return.
 */
final class HrApprovePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:APPROVED,RETURNED'],
            'comments' => ['nullable', 'string', 'max:5000'],
            // Required comment when returning
            'return_comments' => ['required_if:action,RETURNED', 'nullable', 'string', 'min:10', 'max:5000'],
            // Required checkbox acknowledgements when approving
            'checkboxes_checked' => ['required_if:action,APPROVED', 'nullable', 'array', 'min:3'],
            'checkboxes_checked.*' => ['string'],
        ];
    }
}
