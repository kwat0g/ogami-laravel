<?php

declare(strict_types=1);

namespace App\Domains\Budget\Services;

use App\Domains\Budget\Models\AnnualBudget;
use App\Domains\Budget\Models\BudgetAmendment;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Budget Amendment Service — mid-year budget revisions.
 *
 * Types:
 *   - reallocation: zero-sum transfer between GL accounts in same cost center
 *   - increase: additional allocation with VP approval
 *   - decrease: voluntary reduction
 *
 * Workflow: draft -> submitted -> approved | rejected
 * On approval, the underlying AnnualBudget lines are updated.
 */
final class BudgetAmendmentService implements ServiceContract
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = BudgetAmendment::with(['costCenter', 'sourceAccount', 'targetAccount', 'requestedBy'])
            ->orderByDesc('created_at');

        if (isset($filters['fiscal_year'])) {
            $query->where('fiscal_year', $filters['fiscal_year']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['cost_center_id'])) {
            $query->where('cost_center_id', $filters['cost_center_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** @param array<string, mixed> $data */
    public function store(array $data, User $actor): BudgetAmendment
    {
        // For reallocation, source_account_id is required
        if ($data['amendment_type'] === 'reallocation' && empty($data['source_account_id'])) {
            throw new DomainException(
                'Reallocation amendments require a source GL account.',
                'BUDGET_REALLOC_NO_SOURCE',
                422,
            );
        }

        // Validate source budget has enough to transfer
        if ($data['amendment_type'] === 'reallocation') {
            $sourceBudget = AnnualBudget::where('cost_center_id', $data['cost_center_id'])
                ->where('fiscal_year', $data['fiscal_year'])
                ->where('account_id', $data['source_account_id'])
                ->where('status', 'approved')
                ->first();

            if ($sourceBudget === null || $sourceBudget->budgeted_amount_centavos < $data['amount_centavos']) {
                throw new DomainException(
                    'Source budget line has insufficient balance for this reallocation.',
                    'BUDGET_INSUFFICIENT_SOURCE',
                    422,
                );
            }
        }

        return BudgetAmendment::create([
            ...$data,
            'status' => 'draft',
            'requested_by_id' => $actor->id,
            'created_by_id' => $actor->id,
        ]);
    }

    public function submit(BudgetAmendment $amendment): BudgetAmendment
    {
        if ($amendment->status !== 'draft') {
            throw new DomainException('Amendment must be in draft to submit.', 'BUDGET_AMEND_NOT_DRAFT', 422);
        }

        $amendment->update(['status' => 'submitted']);

        return $amendment->fresh() ?? $amendment;
    }

    public function approve(BudgetAmendment $amendment, User $approver, string $remarks = ''): BudgetAmendment
    {
        if ($amendment->status !== 'submitted') {
            throw new DomainException('Amendment must be submitted for approval.', 'BUDGET_AMEND_NOT_SUBMITTED', 422);
        }

        // SoD: approver must differ from requester
        if ($amendment->requested_by_id === $approver->id) {
            throw new DomainException(
                'Approver must differ from the requester.',
                'BUDGET_AMEND_SOD_VIOLATION',
                422,
            );
        }

        return DB::transaction(function () use ($amendment, $approver, $remarks): BudgetAmendment {
            $amendment->update([
                'status' => 'approved',
                'approved_by_id' => $approver->id,
                'approved_at' => now(),
                'approval_remarks' => $remarks,
            ]);

            // Apply the amendment to AnnualBudget lines
            $this->applyAmendment($amendment);

            return $amendment->fresh() ?? $amendment;
        });
    }

    public function reject(BudgetAmendment $amendment, User $approver, string $remarks): BudgetAmendment
    {
        if ($amendment->status !== 'submitted') {
            throw new DomainException('Amendment must be submitted to reject.', 'BUDGET_AMEND_NOT_SUBMITTED', 422);
        }

        $amendment->update([
            'status' => 'rejected',
            'approved_by_id' => $approver->id,
            'approved_at' => now(),
            'approval_remarks' => $remarks,
        ]);

        return $amendment->fresh() ?? $amendment;
    }

    /**
     * Apply the approved amendment to the underlying AnnualBudget lines.
     */
    private function applyAmendment(BudgetAmendment $amendment): void
    {
        $amount = $amendment->amount_centavos;

        if ($amendment->amendment_type === 'reallocation' && $amendment->source_account_id) {
            // Decrease source
            AnnualBudget::where('cost_center_id', $amendment->cost_center_id)
                ->where('fiscal_year', $amendment->fiscal_year)
                ->where('account_id', $amendment->source_account_id)
                ->where('status', 'approved')
                ->decrement('budgeted_amount_centavos', $amount);
        }

        if (in_array($amendment->amendment_type, ['reallocation', 'increase'], true)) {
            // Increase target (or create if doesn't exist)
            $target = AnnualBudget::where('cost_center_id', $amendment->cost_center_id)
                ->where('fiscal_year', $amendment->fiscal_year)
                ->where('account_id', $amendment->target_account_id)
                ->where('status', 'approved')
                ->first();

            if ($target) {
                $target->increment('budgeted_amount_centavos', $amount);
            } else {
                AnnualBudget::create([
                    'cost_center_id' => $amendment->cost_center_id,
                    'fiscal_year' => $amendment->fiscal_year,
                    'account_id' => $amendment->target_account_id,
                    'budgeted_amount_centavos' => $amount,
                    'status' => 'approved',
                    'notes' => "Created via budget amendment #{$amendment->id}",
                    'created_by_id' => $amendment->approved_by_id ?? $amendment->created_by_id,
                ]);
            }
        }

        if ($amendment->amendment_type === 'decrease') {
            AnnualBudget::where('cost_center_id', $amendment->cost_center_id)
                ->where('fiscal_year', $amendment->fiscal_year)
                ->where('account_id', $amendment->target_account_id)
                ->where('status', 'approved')
                ->decrement('budgeted_amount_centavos', $amount);
        }
    }
}
