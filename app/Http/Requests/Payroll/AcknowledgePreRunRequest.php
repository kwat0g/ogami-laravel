<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request for Step 3: Acknowledge pre-run warnings.
 */
final class AcknowledgePreRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Array of warning codes the user has explicitly ticked
            'acknowledged_warnings' => ['required', 'array'],
            'acknowledged_warnings.*' => ['string'],
        ];
    }
}
