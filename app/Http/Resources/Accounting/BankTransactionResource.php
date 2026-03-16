<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounting;

use App\Domains\Accounting\Models\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankTransaction
 */
final class BankTransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var BankTransaction $tx */
        $tx = $this->resource;

        return [
            'id' => $tx->id,
            'bank_account_id' => $tx->bank_account_id,
            'transaction_date' => $tx->transaction_date->toDateString(),
            'description' => $tx->description,
            'amount' => (float) $tx->amount,
            'transaction_type' => $tx->transaction_type,
            'reference_number' => $tx->reference_number,
            'status' => $tx->status,
            'journal_entry_line_id' => $tx->journal_entry_line_id,
            'bank_reconciliation_id' => $tx->bank_reconciliation_id,
            'created_at' => $tx->created_at->toIso8601String(),
        ];
    }
}
