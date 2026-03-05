<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * Read-only query service for payroll aggregations.
 *
 * Encapsulates complex payroll queries used by multiple controllers so that
 * controllers do not import the DB facade directly.
 */
final class PayrollQueryService implements ServiceContract
{
    /**
     * Aggregate YTD payroll totals for an employee in a given year.
     *
     * @return array{
     *   gross: int,
     *   net: int,
     *   sss: int,
     *   philhealth: int,
     *   pagibig: int,
     *   wtax: int,
     *   ytd_taxable_income: int,
     *   ytd_tax_withheld: int,
     * }
     */
    public function ytdForEmployee(int $employeeId, int $year): array
    {
        $sums = DB::table('payroll_details as pd')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pd.payroll_run_id')
            ->where('pd.employee_id', $employeeId)
            ->whereIn('pr.status', ['PUBLISHED', 'completed', 'posted'])
            ->whereYear('pr.pay_date', $year)
            ->select([
                DB::raw('COALESCE(SUM(pd.gross_pay_centavos), 0) as gross'),
                DB::raw('COALESCE(SUM(pd.net_pay_centavos), 0) as net'),
                DB::raw('COALESCE(SUM(pd.sss_ee_centavos), 0) as sss'),
                DB::raw('COALESCE(SUM(pd.philhealth_ee_centavos), 0) as philhealth'),
                DB::raw('COALESCE(SUM(pd.pagibig_ee_centavos), 0) as pagibig'),
                DB::raw('COALESCE(SUM(pd.withholding_tax_centavos), 0) as wtax'),
            ])
            ->first();

        $latest = DB::table('payroll_details as pd')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pd.payroll_run_id')
            ->where('pd.employee_id', $employeeId)
            ->whereIn('pr.status', ['PUBLISHED', 'completed', 'posted'])
            ->whereYear('pr.pay_date', $year)
            ->orderByDesc('pr.pay_date')
            ->select(['pd.ytd_taxable_income_centavos', 'pd.ytd_tax_withheld_centavos'])
            ->first();

        return [
            'gross' => (int) ($sums?->gross ?? 0),
            'net' => (int) ($sums?->net ?? 0),
            'sss' => (int) ($sums?->sss ?? 0),
            'philhealth' => (int) ($sums?->philhealth ?? 0),
            'pagibig' => (int) ($sums?->pagibig ?? 0),
            'wtax' => (int) ($sums?->wtax ?? 0),
            'ytd_taxable_income' => (int) ($latest?->ytd_taxable_income_centavos ?? 0),
            'ytd_tax_withheld' => (int) ($latest?->ytd_tax_withheld_centavos ?? 0),
        ];
    }
}
