<?php

declare(strict_types=1);

namespace App\Http\Resources\Budget;

use App\Domains\Budget\Models\AnnualBudget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AnnualBudget */
final class BudgetLineResource extends JsonResource
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
            'fiscal_year' => $budget->fiscal_year,
            'account_id' => $budget->account_id,
            'budgeted_amount_centavos' => $budget->budgeted_amount_centavos,
            'notes' => $budget->notes,
            'status' => $budget->status,

            'cost_center' => $this->whenLoaded('costCenter', fn () => $budget->costCenter ? [
                'id' => $budget->costCenter->id,
                'ulid' => $budget->costCenter->ulid,
                'name' => $budget->costCenter->name,
                'code' => $budget->costCenter->code,
            ] : null),

            'account' => $this->whenLoaded('account', fn () => $budget->account ? [
                'id' => $budget->account->id,
                'account_code' => $budget->account->account_code,
                'account_name' => $budget->account->account_name,
            ] : null),

            'submitted_by_id' => $budget->submitted_by_id,
            'submitted_by' => $this->whenLoaded('submittedBy', fn () => $budget->submittedBy ? [
                'id' => $budget->submittedBy->id,
                'name' => $budget->submittedBy->name,
            ] : null),
            'submitted_at' => $budget->submitted_at?->toIso8601String(),

            'approved_by_id' => $budget->approved_by_id,
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $budget->approvedBy ? [
                'id' => $budget->approvedBy->id,
                'name' => $budget->approvedBy->name,
            ] : null),
            'approved_at' => $budget->approved_at?->toIso8601String(),
            'approval_remarks' => $budget->approval_remarks,

            'created_by_id' => $budget->created_by_id,
            'created_by' => $this->whenLoaded('createdBy', fn () => $budget->createdBy ? [
                'id' => $budget->createdBy->id,
                'name' => $budget->createdBy->name,
            ] : null),

            'created_at' => $budget->created_at?->toIso8601String(),
            'updated_at' => $budget->updated_at?->toIso8601String(),
        ];
    }
}
