<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Step 04 — Load YTD Accumulators.
 *
 * Reads year-to-date taxable income and withheld tax from prior completed
 * PayrollDetail rows for the same employee in the same calendar year.
 *
 * These accumulator values are required by the TRAIN cumulative withholding
 * method used in Step 14.
 *
 * Uses a date-range predicate (sargable) instead of EXTRACT(year) so the
 * composite index on (status, cutoff_end) can be utilised.
 */
final class Step04LoadYtdStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $year = Carbon::parse($ctx->run->cutoff_end)->year;
        $ytdStart = "{$year}-01-01";
        $ytdEnd = "{$year}-12-31";

        $ytd = DB::table('payroll_details')
            ->join('payroll_runs', 'payroll_details.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_details.employee_id', $ctx->employee->id)
            ->whereBetween('payroll_runs.cutoff_end', [$ytdStart, $ytdEnd])
            ->where('payroll_runs.id', '!=', $ctx->run->id)
            ->where('payroll_runs.status', 'completed')
            ->selectRaw('
                COALESCE(SUM(ytd_taxable_income_centavos), 0) AS ytd_taxable,
                COALESCE(SUM(withholding_tax_centavos), 0)    AS ytd_tax
            ')
            ->first();

        $ctx->ytdTaxableIncomeCentavos = (int) ($ytd->ytd_taxable ?? 0);
        $ctx->ytdTaxWithheldCentavos = (int) ($ytd->ytd_tax ?? 0);

        return $next($ctx);
    }
}
