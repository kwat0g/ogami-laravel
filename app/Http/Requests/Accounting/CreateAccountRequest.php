<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates Chart of Accounts create / update requests.
 *
 * COA-001: code is unique (including archived).
 * COA-006: hierarchy depth ≤ 5 — the service handles this; FormRequest pre-checks parent.
 */
final class CreateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $accountId = $this->route('account')?->id; // null on create, set on update

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9\-]+$/',
                // COA-001: unique across all rows including soft-deleted
                Rule::unique('chart_of_accounts', 'code')->ignore($accountId),
            ],
            'name' => ['required', 'string', 'max:200'],
            'account_type' => ['required', Rule::in(['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'COGS', 'OPEX', 'TAX'])],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:chart_of_accounts,id,deleted_at,NULL',
                // Cannot be the account itself (circular)
                Rule::notIn([$accountId]),
            ],
            'normal_balance' => ['required', Rule::in(['DEBIT', 'CREDIT'])],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'This account code is already in use (including archived accounts). Account codes are permanent identifiers. (COA-001)',
            'code.regex' => 'Account code may only contain letters, numbers, and hyphens.',
            'parent_id.not_in' => 'An account cannot be its own parent.',
        ];
    }
}
