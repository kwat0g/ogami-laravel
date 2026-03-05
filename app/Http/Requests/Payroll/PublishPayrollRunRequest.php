<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request for Step 8: Publish payslips (with optional scheduling).
 */
final class PublishPayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'publish_at' => ['nullable', 'date', 'after:now'],
            'notify_email' => ['boolean'],
            'notify_in_app' => ['boolean'],
        ];
    }
}
