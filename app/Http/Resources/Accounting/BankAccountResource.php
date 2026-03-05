<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Accounting\Models\BankAccount
 */
final class BankAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \App\Domains\Accounting\Models\BankAccount $account */
        $account = $this->resource;

        return [
            'id' => $account->id,
            'name' => $account->name,
            'account_number' => $account->account_number,
            'bank_name' => $account->bank_name,
            'account_type' => $account->account_type,
            'account_id' => $account->account_id,
            'opening_balance' => (float) $account->opening_balance,
            'is_active' => $account->is_active,
            'chart_account' => $this->whenLoaded('chartAccount', fn () => [
                'id' => $account->chartAccount->id,
                'code' => $account->chartAccount->code,
                'name' => $account->chartAccount->name,
            ]),
            'created_at' => $account->created_at->toIso8601String(),
            'updated_at' => $account->updated_at->toIso8601String(),
        ];
    }
}
