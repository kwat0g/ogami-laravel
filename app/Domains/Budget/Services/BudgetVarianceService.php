<?php

declare(strict_types=1);

namespace App\Domains\Budget\Services;

use App\Domains\Accounting\Models\JournalEntryLine;
use App\Domains\Budget\Models\AnnualBudget;
use App\Domains\Budget\Models\CostCenter;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Budget Variance Analysis Service.
 *
 * Compares budgeted amounts against actual GL spend per cost center, GL account,
 * and fiscal year. Returns variance (budget - actual) and utilization percentages.
 *
 * All monetary amounts are in centavos (integer) to match the Money VO pattern.
 */
final class BudgetVarianceService implements ServiceContract
{
    /**
     * Get variance analysis for a fiscal year, optionally filtered by cost center or department.
     *
     * @param array{
     *     fiscal_year: int,
     *     cost_center_id?: int,
     *     department_id?: int,
     *     account_id?: int,
     * } $filters
     *
     * @return Collection<int, array{
     *     cost_center_id: int,
     *     cost_center_name: string,
     *     cost_center_code: string,
     *     account_id: int,
     *     account_code: string,
     *     account_name: string,
     *     budgeted_centavos: int,
     *     actual_centavos: int,
     *     variance_centavos: int,
     *     utilization_pct: float,
     *     status: string,
     * }>
     */
    public function varianceReport(array $filters): Collection
    {
        $year = $filters['fiscal_year'];

        // 1. Get all approved budget lines for the year
        $budgets = AnnualBudget::query()
            ->where('fiscal_year', $year)
            ->where('status', 'approved')
            ->when($filters['cost_center_id'] ?? null, fn ($q, $v) => $q->where('cost_center_id', $v))
            ->when($filters['account_id'] ?? null, fn ($q, $v) => $q->where('account_id', $v))
            ->when($filters['department_id'] ?? null, function ($q, $deptId) {
                $q->whereHas('costCenter', fn ($q2) => $q2->where('department_id', $deptId));
            })
            ->with(['costCenter', 'account'])
            ->get();

        if ($budgets->isEmpty()) {
            return collect();
        }

        // 2. Gather actual GL spend per account for the fiscal year
        // Actual spend = sum of debit lines on posted JEs for matching accounts in the year's fiscal periods
        $accountIds = $budgets->pluck('account_id')->unique()->values()->toArray();

        $actuals = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('fiscal_periods as fp', 'fp.id', '=', 'je.fiscal_period_id')
            ->where('je.status', 'posted')
            ->whereNull('je.deleted_at')
            ->where('fp.fiscal_year', $year)
            ->whereIn('jel.account_id', $accountIds)
            ->select(
                'jel.account_id',
                DB::raw('COALESCE(SUM(jel.debit_centavos), 0) - COALESCE(SUM(jel.credit_centavos), 0) as net_debit_centavos'),
            )
            ->groupBy('jel.account_id')
            ->get()
            ->keyBy('account_id');

        // 3. Map budget lines to variance rows
        return $budgets->map(function (AnnualBudget $budget) use ($actuals) {
            $budgeted = $budget->budgeted_amount_centavos;
            $actualRow = $actuals->get($budget->account_id);
            $actual = $actualRow ? (int) $actualRow->net_debit_centavos : 0;
            $actual = max(0, $actual); // Only count positive (expense) amounts

            $variance = $budgeted - $actual;
            $utilization = $budgeted > 0 ? round(($actual / $budgeted) * 100, 2) : 0.0;

            // Determine status: under_budget, on_track, warning (>80%), over_budget
            $status = match (true) {
                $utilization > 100.0 => 'over_budget',
                $utilization >= 90.0 => 'critical',
                $utilization >= 80.0 => 'warning',
                $utilization >= 50.0 => 'on_track',
                default => 'under_budget',
            };

            return [
                'cost_center_id'   => $budget->cost_center_id,
                'cost_center_name' => $budget->costCenter->name ?? '—',
                'cost_center_code' => $budget->costCenter->code ?? '—',
                'account_id'       => $budget->account_id,
                'account_code'     => $budget->account->code ?? '—',
                'account_name'     => $budget->account->name ?? '—',
                'budgeted_centavos' => $budgeted,
                'actual_centavos'  => $actual,
                'variance_centavos' => $variance,
                'utilization_pct'  => $utilization,
                'status'           => $status,
            ];
        })
            ->sortByDesc('utilization_pct')
            ->values();
    }

    /**
     * Summarize variance by cost center (aggregate all GL accounts).
     *
     * @return Collection<int, array{
     *     cost_center_id: int,
     *     cost_center_name: string,
     *     cost_center_code: string,
     *     total_budgeted_centavos: int,
     *     total_actual_centavos: int,
     *     total_variance_centavos: int,
     *     utilization_pct: float,
     *     line_count: int,
     *     over_budget_lines: int,
     * }>
     */
    public function varianceByCostCenter(int $fiscalYear, ?int $departmentId = null): Collection
    {
        $detail = $this->varianceReport([
            'fiscal_year' => $fiscalYear,
            'department_id' => $departmentId,
        ]);

        return $detail
            ->groupBy('cost_center_id')
            ->map(function (Collection $lines) {
                $first = $lines->first();
                $totalBudget = $lines->sum('budgeted_centavos');
                $totalActual = $lines->sum('actual_centavos');

                return [
                    'cost_center_id'          => $first['cost_center_id'],
                    'cost_center_name'        => $first['cost_center_name'],
                    'cost_center_code'        => $first['cost_center_code'],
                    'total_budgeted_centavos' => $totalBudget,
                    'total_actual_centavos'   => $totalActual,
                    'total_variance_centavos' => $totalBudget - $totalActual,
                    'utilization_pct'         => $totalBudget > 0
                        ? round(($totalActual / $totalBudget) * 100, 2)
                        : 0.0,
                    'line_count'              => $lines->count(),
                    'over_budget_lines'       => $lines->where('status', 'over_budget')->count(),
                ];
            })
            ->sortByDesc('utilization_pct')
            ->values();
    }

    /**
     * Forecast year-end spend based on current run-rate.
     *
     * Uses elapsed months in the fiscal year to project annual spend.
     *
     * @return Collection<int, array{
     *     cost_center_name: string,
     *     account_name: string,
     *     budgeted_centavos: int,
     *     actual_centavos: int,
     *     projected_centavos: int,
     *     projected_variance_centavos: int,
     *     months_elapsed: int,
     * }>
     */
    public function yearEndForecast(int $fiscalYear): Collection
    {
        $currentMonth = (int) now()->format('n');
        $currentYear = (int) now()->format('Y');

        // How many months have elapsed in this fiscal year
        $monthsElapsed = $currentYear === $fiscalYear
            ? max(1, $currentMonth)
            : ($currentYear > $fiscalYear ? 12 : 0);

        if ($monthsElapsed === 0) {
            return collect();
        }

        $detail = $this->varianceReport(['fiscal_year' => $fiscalYear]);

        return $detail->map(function (array $row) use ($monthsElapsed) {
            $monthlyRate = $row['actual_centavos'] / $monthsElapsed;
            $projected = (int) round($monthlyRate * 12);

            return [
                'cost_center_name'          => $row['cost_center_name'],
                'account_name'              => $row['account_name'],
                'budgeted_centavos'         => $row['budgeted_centavos'],
                'actual_centavos'           => $row['actual_centavos'],
                'projected_centavos'        => $projected,
                'projected_variance_centavos' => $row['budgeted_centavos'] - $projected,
                'months_elapsed'            => $monthsElapsed,
            ];
        })->values();
    }
}
