<?php

declare(strict_types=1);

namespace App\Http\Resources\Tax;

use App\Domains\Tax\Models\VatLedger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VatLedger
 */
final class VatLedgerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var VatLedger $ledger */
        $ledger = $this->resource;

        return [
            'id' => $ledger->id,
            'fiscal_period_id' => $ledger->fiscal_period_id,
            'input_vat' => (float) $ledger->input_vat,
            'output_vat' => (float) $ledger->output_vat,
            'net_vat' => (float) $ledger->net_vat,
            'carry_forward_from_prior' => (float) $ledger->carry_forward_from_prior,
            // VAT-004: actual amount due or to carry forward
            'vat_payable' => $ledger->vat_payable,
            'is_closed' => $ledger->is_closed,
            'closed_at' => $ledger->closed_at?->toIso8601String(),
            'closed_by' => $this->whenLoaded('closedByUser', fn () => [
                'id' => $ledger->closedByUser->id,
                'name' => $ledger->closedByUser->name,
            ]),
            'created_at' => $ledger->created_at->toIso8601String(),
            'updated_at' => $ledger->updated_at->toIso8601String(),
        ];
    }
}
