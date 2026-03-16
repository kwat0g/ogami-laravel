<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounting;

use App\Domains\Accounting\Models\BankReconciliation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankReconciliation
 */
final class BankReconciliationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var BankReconciliation $rec */
        $rec = $this->resource;

        return [
            'id' => $rec->id,
            'ulid' => $rec->ulid,
            'bank_account_id' => $rec->bank_account_id,
            'period_from' => $rec->period_from->toDateString(),
            'period_to' => $rec->period_to->toDateString(),
            'opening_balance' => (float) $rec->opening_balance,
            'closing_balance' => (float) $rec->closing_balance,
            'status' => $rec->status,
            'created_by' => $rec->created_by,
            'certified_by' => $rec->certified_by,
            'certified_at' => $rec->certified_at?->toIso8601String(),
            'notes' => $rec->notes,
            'bank_account' => $this->whenLoaded('bankAccount', fn () => new BankAccountResource($rec->bankAccount)),
            'transactions' => $this->whenLoaded(
                'transactions',
                fn () => BankTransactionResource::collection($rec->transactions)
            ),
            'unmatched_count' => $rec->unmatchedCount(),
            'created_at' => $rec->created_at->toIso8601String(),
            'updated_at' => $rec->updated_at->toIso8601String(),
        ];
    }
}
