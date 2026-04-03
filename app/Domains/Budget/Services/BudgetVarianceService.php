<?php

declare(strict_types=1);

namespace App\Domains\Budget\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BudgetVarianceService implements ServiceContract
{
    /**
     * Detailed variance rows by budget line.
     *
     * @param array{fiscal_year:int, cost_center_id?:int, department_id?:int, account_id?:int} $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function varianceReport(array $filters): Collection
    {
        $rows = DB::table('annual_budgets')
            ->join('cost_centers', 'annual_budgets.cost_center_id', '=', 'cost_centers.id')
            ->join('chart_of_accounts', 'annual_budgets.account_id', '=', 'chart_of_accounts.id')
            ->where('annual_budgets.fiscal_year', (int) $filters['fiscal_year'])
            ->whereIn('annual_budgets.status', ['approved', 'locked'])
            ->when(isset($filters['cost_center_id']), fn ($q) => $q->where('annual_budgets.cost_center_id', (int) $filters['cost_center_id']))
            ->when(isset($filters['department_id']), fn ($q) => $q->where('cost_centers.department_id', (int) $filters['department_id']))
            ->when(isset($filters['account_id']), fn ($q) => $q->where('annual_budgets.account_id', (int) $filters['account_id']))
            ->select([
                'annual_budgets.id',
                'annual_budgets.cost_center_id',
                'annual_budgets.account_id',
                'annual_budgets.fiscal_year',
                'annual_budgets.status as budget_status',
                'cost_centers.name as cost_center_name',
                'chart_of_accounts.code as account_code',
                'chart_of_accounts.name as account_name',
                'annual_budgets.budgeted_amount_centavos',
            ])
            ->orderBy('cost_centers.name')
            ->orderBy('chart_of_accounts.code')
            ->get();

        return $rows->map(function ($row): array {
            $budgeted = (int) $row->budgeted_amount_centavos;
            $actual = 0; // TODO: wire to posted actuals ledger when available.
            $variance = $budgeted - $actual;

            return [
                'id' => (int) $row->id,
                'cost_center_id' => (int) $row->cost_center_id,
                'account_id' => (int) $row->account_id,
                'fiscal_year' => (int) $row->fiscal_year,
                'cost_center_name' => (string) $row->cost_center_name,
                'account_code' => (string) $row->account_code,
                'account_name' => (string) $row->account_name,
                'budgeted_centavos' => $budgeted,
                'actual_centavos' => $actual,
                'variance_centavos' => $variance,
                'status' => $variance >= 0 ? 'under_budget' : 'over_budget',
                'budget_status' => (string) $row->budget_status,
            ];
        })->values();
    }

    /**
     * Return budget variance aggregated by cost center for a given fiscal year.
     * 
     * @param int $year
     * @param int|null $departmentId
     * @return Collection<int, array<string, mixed>>
     */
    public function varianceByCostCenter(int $year, ?int $departmentId = null): Collection
    {
        $rows = DB::table('annual_budgets')
            ->join('cost_centers', 'annual_budgets.cost_center_id', '=', 'cost_centers.id')
            ->where('annual_budgets.fiscal_year', $year)
            ->whereIn('annual_budgets.status', ['approved', 'locked'])
            ->when($departmentId !== null, fn ($q) => $q->where('cost_centers.department_id', $departmentId))
            ->select(
                'cost_centers.id as cost_center_id',
                'cost_centers.name as cost_center',
                DB::raw('COALESCE(SUM(annual_budgets.budgeted_amount_centavos), 0) as total_budgeted_centavos'),
                DB::raw('COUNT(annual_budgets.id) as line_count')
            )
            ->groupBy('cost_centers.id', 'cost_centers.name')
            ->orderByDesc('total_budgeted_centavos')
            ->get();

        return $rows->map(fn ($row): array => [
            'cost_center_id' => (int) $row->cost_center_id,
            'cost_center' => (string) $row->cost_center,
            'total_budgeted_centavos' => (int) $row->total_budgeted_centavos,
            'line_count' => (int) $row->line_count,
            'actual_spent_centavos' => 0,
            'variance_centavos' => (int) $row->total_budgeted_centavos,
        ])->values();
    }

    /**
     * Basic year-end forecast per approved budget line.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function yearEndForecast(int $year): Collection
    {
        $monthsElapsed = max(1, (int) now()->month);

        return $this->varianceReport(['fiscal_year' => $year])
            ->map(function (array $row) use ($monthsElapsed): array {
                $actual = (int) $row['actual_centavos'];
                $projected = (int) round(($actual / $monthsElapsed) * 12);

                return [
                    'cost_center_id' => (int) $row['cost_center_id'],
                    'cost_center_name' => (string) $row['cost_center_name'],
                    'account_id' => (int) $row['account_id'],
                    'account_name' => (string) $row['account_name'],
                    'budgeted_centavos' => (int) $row['budgeted_centavos'],
                    'actual_centavos' => $actual,
                    'projected_centavos' => $projected,
                    'projected_variance_centavos' => (int) $row['budgeted_centavos'] - $projected,
                    'months_elapsed' => $monthsElapsed,
                ];
            })
            ->values();
    }
}
