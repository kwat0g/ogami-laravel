<?php

declare(strict_types=1);

namespace App\Http\Requests\AR;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AR-006: bad debt write-off payload.
 */
class WriteOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'write_off_reason' => ['required', 'string', 'max:500'],
            'bad_debt_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'ar_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
        ];
    }
}
