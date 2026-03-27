<?php

declare(strict_types=1);

namespace App\Http\Resources\Budget;

use App\Domains\Budget\Models\AnnualBudget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AnnualBudgetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AnnualBudget $budget */
        $budget = $this->resource;

        return [
            'id' => $budget->id,
            'ulid' => $budget->ulid,
            'cost_center_id' => $budget->cost_center_id,
            'cost_center' => $this->whenLoaded('costCenter', fn () => $budget->costCenter ? [
                'id' => $budget->costCenter->id,
                'name' => $budget->costCenter->name,
                'code' => $budget->costCenter->code,
            ] : null),
            'account_id' => $budget->account_id,
            'account' => $this->whenLoaded('account', fn () => $budget->account ? [
                'id' => $budget->account->id,
                'account_code' => $budget->account->account_code ?? null,
                'name' => $budget->account->name ?? $budget->account->account_name ?? null,
            ] : null),
            'fiscal_year' => $budget->fiscal_year,
            'budgeted_amount_centavos' => $budget->budgeted_amount_centavos,
            'budgeted_amount' => $budget->budgeted_amount_centavos / 100,
            'status' => $budget->status,
            'notes' => $budget->notes,

            'submitted_by_id' => $budget->submitted_by_id,
            'submitted_at' => $budget->submitted_at,
            'approved_by_id' => $budget->approved_by_id,
            'approved_at' => $budget->approved_at,
            'approval_remarks' => $budget->approval_remarks,

            'created_at' => $budget->created_at,
            'updated_at' => $budget->updated_at,
        ];
    }
}
