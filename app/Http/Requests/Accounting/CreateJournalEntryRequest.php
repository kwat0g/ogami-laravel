<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Rules\Accounting\LeafAccountRule;
use App\Rules\Accounting\NotFuturePeriodRule;
use App\Rules\Accounting\OpenFiscalPeriodRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates Journal Entry create requests.
 *
 * JE-002: min 2 lines.
 * JE-003: each line must have either debit > 0 or credit > 0 (not both, not neither).
 * JE-004: date must fall in an open fiscal period (OpenFiscalPeriodRule).
 * JE-005: date cannot be future unless setting allows (NotFuturePeriodRule).
 * COA-002: account_id per line must be a leaf account (LeafAccountRule).
 */
final class CreateJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date',
                new OpenFiscalPeriodRule,   // JE-004
                new NotFuturePeriodRule,    // JE-005
            ],
            'description' => ['required', 'string', 'min:5', 'max:500'],
            // JE-002: minimum 2 lines
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => [
                'required',
                'integer',
                'exists:chart_of_accounts,id,deleted_at,NULL',
                new LeafAccountRule,        // COA-002
            ],
            // JE-003: debit/credit must exist and be > 0 if provided
            'lines.*.debit' => ['nullable', 'numeric', 'min:0.0001', 'decimal:0,4'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0.0001', 'decimal:0,4'],
            'lines.*.cost_center_id' => [
                'nullable',
                'integer',
                'exists:cost_centers,id',
            ],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.min' => 'A journal entry must have at least 2 lines (one debit, one credit). (JE-002)',
            'lines.*.account_id.exists' => 'The selected account does not exist or has been archived.',
            'lines.*.debit.min' => 'Line debit amount must be greater than zero. (JE-003)',
            'lines.*.credit.min' => 'Line credit amount must be greater than zero. (JE-003)',
        ];
    }

    /**
     * Additional validation: each line must have either debit OR credit — not both, not neither.
     * This cannot be expressed as a rule array, so we use withValidator.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $lines = $this->input('lines', []);
            foreach ($lines as $index => $line) {
                $hasDebit = ! empty($line['debit']);
                $hasCredit = ! empty($line['credit']);

                if ($hasDebit && $hasCredit) {
                    $v->errors()->add(
                        "lines.{$index}",
                        'Line '.($index + 1).' cannot have both debit and credit. (JE-003)'
                    );
                } elseif (! $hasDebit && ! $hasCredit) {
                    $v->errors()->add(
                        "lines.{$index}",
                        'Line '.($index + 1).' must have either a debit or a credit amount. (JE-003)'
                    );
                }
            }
        });
    }
}
