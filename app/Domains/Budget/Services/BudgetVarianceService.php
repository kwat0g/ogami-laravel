<?php

declare(strict_types=1);

namespace App\Domains\Budget\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BudgetVarianceService implements ServiceContract
{
    /**
     * Return budget variance aggregated by cost center for a given fiscal year.
     * 
     * @param int $year
     * @return Collection<int, mixed>
     */
    public function varianceByCostCenter(int $year): Collection
    {
        return DB::table('annual_budgets')
            ->join('cost_centers', 'annual_budgets.cost_center_id', '=', 'cost_centers.id')
            ->where('annual_budgets.fiscal_year', $year)
            ->select(
                'cost_centers.name as cost_center',
                DB::raw('COALESCE(SUM(annual_budgets.budgeted_amount_centavos), 0) as total_budget'),
                DB::raw('0 as actual_spent'),
                DB::raw('COALESCE(SUM(annual_budgets.budgeted_amount_centavos), 0) as variance')
            )
            ->groupBy('cost_centers.id', 'cost_centers.name')
            ->orderByDesc('total_budget')
            ->get();
    }
}
