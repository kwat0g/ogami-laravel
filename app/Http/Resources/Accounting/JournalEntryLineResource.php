<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounting;

use App\Domains\Accounting\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JournalEntryLine */
final class JournalEntryLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account_code' => $this->whenLoaded('account', fn () => $this->account->code),
            'account_name' => $this->whenLoaded('account', fn () => $this->account->name),
            'debit' => $this->debit,
            'credit' => $this->credit,
            'cost_center_id' => $this->cost_center_id,
            'description' => $this->description,
        ];
    }
}
