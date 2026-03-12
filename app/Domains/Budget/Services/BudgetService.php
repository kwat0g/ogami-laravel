<?php

declare(strict_types=1);

namespace App\Domains\Budget\Services;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEntryLine;
use App\Domains\Budget\Models\AnnualBudget;
use App\Domains\Budget\Models\CostCenter;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

final class BudgetService implements ServiceContract
{
    // ── Cost Centre CRUD ──────────────────────────────────────────────────────

    public function storeCostCenter(array $data, User $actor): CostCenter
    {
        return DB::transaction(function () use ($data, $actor): CostCenter {
            return CostCenter::create([
                'name'          => $data['name'],
                'code'          => strtoupper($data['code']),
                'description'   => $data['description'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'parent_id'     => $data['parent_id'] ?? null,
                'is_active'     => $data['is_active'] ?? true,
                'created_by_id' => $actor->id,
            ]);
        });
    }

    public function updateCostCenter(CostCenter $costCenter, array $data, User $actor): CostCenter
    {
        return DB::transaction(function () use ($costCenter, $data): CostCenter {
            $costCenter->update([
                'name'          => $data['name'] ?? $costCenter->name,
                'code'          => isset($data['code']) ? strtoupper($data['code']) : $costCenter->code,
                'description'   => $data['description'] ?? $costCenter->description,
                'department_id' => array_key_exists('department_id', $data)
                    ? $data['department_id']
                    : $costCenter->department_id,
                'parent_id'     => array_key_exists('parent_id', $data)
                    ? $data['parent_id']
                    : $costCenter->parent_id,
                'is_active'     => $data['is_active'] ?? $costCenter->is_active,
            ]);

            return $costCenter->fresh();
        });
    }

    // ── Annual Budget CRUD ────────────────────────────────────────────────────

    /**
     * Upsert a budget line: one record per (cost_center, fiscal_year, account).
     */
    public function setBudgetLine(array $data, User $actor): AnnualBudget
    {
        return DB::transaction(function () use ($data, $actor): AnnualBudget {
            /** @var AnnualBudget $budget */
            $budget = AnnualBudget::firstOrNew([
                'cost_center_id' => $data['cost_center_id'],
                'fiscal_year'    => $data['fiscal_year'],
                'account_id'     => $data['account_id'],
            ]);

            $isNew = ! $budget->exists;

            $budget->fill([
                'budgeted_amount_centavos' => $data['budgeted_amount_centavos'],
                'notes'                    => $data['notes'] ?? $budget->notes,
                'created_by_id'            => $isNew ? $actor->id : $budget->created_by_id,
                'updated_by_id'            => $actor->id,
            ]);

            $budget->save();

            return $budget->fresh();
        });
    }

    // ── Approval Workflow ─────────────────────────────────────────────────────

    /**
     * Submit a budget line for VP approval (draft → submitted).
     */
    public function submitBudget(AnnualBudget $budget, User $actor): AnnualBudget
    {
        if ($budget->status !== 'draft' && $budget->status !== 'rejected') {
            throw new \App\Shared\Exceptions\DomainException(
                'Only draft or rejected budgets can be submitted.',
                'BUDGET_INVALID_STATUS',
                422,
            );
        }

        return DB::transaction(function () use ($budget, $actor): AnnualBudget {
            $budget->update([
                'status'          => 'submitted',
                'submitted_by_id' => $actor->id,
                'submitted_at'    => now(),
                'approved_by_id'  => null,
                'approved_at'     => null,
                'approval_remarks' => null,
            ]);

            return $budget->fresh();
        });
    }

    /**
     * VP approves a submitted budget line (submitted → approved).
     */
    public function approveBudget(AnnualBudget $budget, User $actor, ?string $remarks = null): AnnualBudget
    {
        if ($budget->status !== 'submitted') {
            throw new \App\Shared\Exceptions\DomainException(
                'Only submitted budgets can be approved.',
                'BUDGET_INVALID_STATUS',
                422,
            );
        }

        if ($budget->submitted_by_id === $actor->id) {
            throw new \App\Shared\Exceptions\DomainException(
                'The submitter cannot also approve the budget (SOD).',
                'SOD_VIOLATION',
                403,
            );
        }

        return DB::transaction(function () use ($budget, $actor, $remarks): AnnualBudget {
            $budget->update([
                'status'           => 'approved',
                'approved_by_id'   => $actor->id,
                'approved_at'      => now(),
                'approval_remarks' => $remarks,
            ]);

            return $budget->fresh();
        });
    }

    /**
     * VP rejects a submitted budget line (submitted → rejected).
     */
    public function rejectBudget(AnnualBudget $budget, User $actor, ?string $remarks = null): AnnualBudget
    {
        if ($budget->status !== 'submitted') {
            throw new \App\Shared\Exceptions\DomainException(
                'Only submitted budgets can be rejected.',
                'BUDGET_INVALID_STATUS',
                422,
            );
        }

        return DB::transaction(function () use ($budget, $actor, $remarks): AnnualBudget {
            $budget->update([
                'status'           => 'rejected',
                'approved_by_id'   => $actor->id,
                'approved_at'      => now(),
                'approval_remarks' => $remarks,
            ]);

            return $budget->fresh();
        });
    }

    // ── Budget Utilisation ────────────────────────────────────────────────────

    /**
     * Return budget utilisation for a cost center in a given fiscal year.
     *
     * Each element in the returned array represents one budgeted account:
     * [
     *   'account_id'               => int,
     *   'account_code'             => string,
     *   'account_name'             => string,
     *   'normal_balance'           => 'DEBIT'|'CREDIT',
     *   'budgeted_amount_centavos' => int,
     *   'actual_amount_centavos'   => int,
     *   'variance_centavos'        => int,   // budget - actual (positive = under)
     *   'utilisation_pct'          => float, // 0–100+ (>100 = over budget)
     * ]
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUtilisation(CostCenter $costCenter, int $fiscalYear): array
    {
        // Load all budget lines for the cost center + year with account data
        $budgets = AnnualBudget::with('account')
            ->where('cost_center_id', $costCenter->id)
            ->where('fiscal_year', $fiscalYear)
            ->get();

        if ($budgets->isEmpty()) {
            return [];
        }

        // Aggregate posted JEL actuals for this cost center grouped by account
        // JEL debit/credit are stored as decimal (not centavos); multiply by 100
        // to convert to centavos for comparison.
        $postedJeIds = JournalEntry::query()
            ->where('status', 'posted')
            ->whereYear('posted_at', $fiscalYear)
            ->pluck('id');

        if ($postedJeIds->isEmpty()) {
            $actuals = [];
        } else {
            $actuals = JournalEntryLine::query()
                ->selectRaw('account_id, SUM(COALESCE(debit,0)) as total_debit, SUM(COALESCE(credit,0)) as total_credit')
                ->where('cost_center_id', $costCenter->id)
                ->whereIn('journal_entry_id', $postedJeIds)
                ->groupBy('account_id')
                ->get()
                ->keyBy('account_id')
                ->toArray();
        }

        $result = [];

        foreach ($budgets as $budget) {
            $account = $budget->account;
            $row     = $actuals[$budget->account_id] ?? null;

            $totalDebit  = (float) ($row['total_debit']  ?? 0);
            $totalCredit = (float) ($row['total_credit'] ?? 0);

            // Net activity in centavos based on the account's normal balance side
            $activityCentavos = $account->normal_balance === 'DEBIT'
                ? (int) round(($totalDebit - $totalCredit) * 100)
                : (int) round(($totalCredit - $totalDebit) * 100);

            $budgetedCentavos = $budget->budgeted_amount_centavos;
            $varianceCentavos = $budgetedCentavos - $activityCentavos;
            $utilisationPct   = $budgetedCentavos > 0
                ? round($activityCentavos / $budgetedCentavos * 100, 2)
                : 0.0;

            $result[] = [
                'budget_ulid'              => $budget->ulid,
                'account_id'               => $budget->account_id,
                'account_code'             => $account->account_code ?? '',
                'account_name'             => $account->account_name ?? '',
                'normal_balance'           => $account->normal_balance,
                'budgeted_amount_centavos' => $budgetedCentavos,
                'actual_amount_centavos'   => $activityCentavos,
                'variance_centavos'        => $varianceCentavos,
                'utilisation_pct'          => $utilisationPct,
            ];
        }

        return $result;
    }

    /**
     * Quick check: is there remaining budget for a given cost_center + account + year?
     * Used by procurement and AP services before approving spend.
     */
    public function hasAvailableBudget(
        int $costCenterId,
        int $accountId,
        int $fiscalYear,
        int $requestedCentavos
    ): bool {
        $budget = AnnualBudget::query()
            ->where('cost_center_id', $costCenterId)
            ->where('account_id', $accountId)
            ->where('fiscal_year', $fiscalYear)
            ->first();

        if ($budget === null) {
            return true; // No budget defined → no restriction
        }

        $postedJeIds = JournalEntry::query()
            ->where('status', 'posted')
            ->whereYear('posted_at', $fiscalYear)
            ->pluck('id');

        $spent = 0;

        if ($postedJeIds->isNotEmpty()) {
            $row = JournalEntryLine::query()
                ->selectRaw('SUM(COALESCE(debit,0)) as total_debit, SUM(COALESCE(credit,0)) as total_credit')
                ->where('cost_center_id', $costCenterId)
                ->where('account_id', $accountId)
                ->whereIn('journal_entry_id', $postedJeIds)
                ->first()
                ?->toArray();

            if ($row !== null) {
                $spent = (int) round(((float) $row['total_debit'] - (float) $row['total_credit']) * 100);
            }
        }

        return ($spent + $requestedCentavos) <= $budget->budgeted_amount_centavos;
    }
}
