<?php

declare(strict_types=1);

namespace App\Domains\Budget\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Budget Forecast Service — projects year-end spend based on current burn rate.
 */
final class BudgetForecastService implements ServiceContract
{
    /**
     * Forecast year-end spend for all budget lines in a given fiscal year.
     *
     * Uses actual spend through current month and projects linearly for remaining months.
     *
     * @return Collection<int, array{cost_center: string, account: string, budgeted_centavos: int, actual_ytd_centavos: int, months_elapsed: int, months_remaining: int, monthly_burn_rate_centavos: int, forecasted_year_end_centavos: int, variance_centavos: int, on_track: bool}>
     */
    public function forecastByBudgetLine(int $fiscalYear): Collection
    {
        $currentMonth = (int) now()->format('n');
        $monthsElapsed = max(1, $currentMonth);
        $monthsRemaining = max(0, 12 - $currentMonth);

        try {
            $budgetLines = DB::table('annual_budgets')
                ->join('cost_centers', 'annual_budgets.cost_center_id', '=', 'cost_centers.id')
                ->join('chart_of_accounts', 'annual_budgets.account_id', '=', 'chart_of_accounts.id')
                ->where('annual_budgets.fiscal_year', $fiscalYear)
                ->whereNull('annual_budgets.deleted_at')
                ->select(
                    'annual_budgets.id',
                    'annual_budgets.cost_center_id',
                    'cost_centers.name as cost_center_name',
                    'annual_budgets.account_id',
                    'chart_of_accounts.name as account_name',
                    'annual_budgets.budgeted_amount_centavos'
                )
                ->get();
        } catch (\Throwable $e) {
            // Table or columns may not exist yet (migration not run)
            \Illuminate\Support\Facades\Log::warning('[Budget] Forecast query failed: ' . $e->getMessage());

            return collect([]);
        }

        if ($budgetLines->isEmpty()) {
            return collect([]);
        }

        // Get actual spend per budget line from journal entries
        try {
            $actualSpend = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->join('fiscal_periods', 'journal_entries.fiscal_period_id', '=', 'fiscal_periods.id')
                ->where('journal_entries.status', 'posted')
                ->where('fiscal_periods.fiscal_year', $fiscalYear)
                ->whereNull('journal_entries.deleted_at')
                ->select(
                    'journal_entry_lines.account_id',
                    DB::raw('COALESCE(SUM(journal_entry_lines.debit_centavos), 0) as total_debit')
                )
                ->groupBy('journal_entry_lines.account_id')
                ->pluck('total_debit', 'account_id');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Budget] Actual spend query failed: ' . $e->getMessage());
            $actualSpend = collect([]);
        }

        return $budgetLines->map(function ($line) use ($actualSpend, $monthsElapsed, $monthsRemaining) {
            $budgeted = (int) $line->budgeted_amount_centavos;
            $actual = (int) ($actualSpend[$line->account_id] ?? 0);
            $monthlyBurn = $monthsElapsed > 0 ? (int) round($actual / $monthsElapsed) : 0;
            $forecasted = $actual + ($monthlyBurn * $monthsRemaining);
            $variance = $budgeted - $forecasted;

            return [
                'cost_center' => $line->cost_center_name,
                'account' => $line->account_name,
                'budgeted_centavos' => $budgeted,
                'actual_ytd_centavos' => $actual,
                'months_elapsed' => $monthsElapsed,
                'months_remaining' => $monthsRemaining,
                'monthly_burn_rate_centavos' => $monthlyBurn,
                'forecasted_year_end_centavos' => $forecasted,
                'variance_centavos' => $variance,
                'on_track' => $variance >= 0,
            ];
        });
    }
}
