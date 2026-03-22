<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Step 18 — 13th Month Pay (Year-End Bonus).
 *
 * Computed and paid on December 2nd cutoff only.
 * Formula: Total basic pay for the year ÷ 12
 *
 * Requirements:
 *   - Employee must have worked at least 1 month in the calendar year
 *   - Pro-rated for employees who joined mid-year
 *   - Tax treatment: First ₱90,000 is tax-exempt (RR 3-2015), excess taxable
 *
 * This step runs after net pay and adds 13th month as a separate earning,
 * storing it in the payroll_details.thirteenth_month_centavos column.
 */
final class Step18ThirteenthMonthStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        // Only compute on December 2nd cutoff
        if (! $ctx->isDecemberSecondCutoff) {
            return $next($ctx);
        }

        // Get year-to-date basic pay from completed payroll runs
        $year = (int) date('Y', strtotime($ctx->run->cutoff_end));
        $yearStart = "{$year}-01-01";
        $yearEnd = "{$year}-12-31";

        $ytdBasicPay = DB::table('payroll_details')
            ->join('payroll_runs', 'payroll_details.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_details.employee_id', $ctx->employee->id)
            ->whereBetween('payroll_runs.cutoff_end', [$yearStart, $yearEnd])
            ->where('payroll_runs.status', 'completed')
            ->sum('payroll_details.basic_pay_centavos');

        // Include current period's basic pay
        $totalBasicPayForYear = $ytdBasicPay + $ctx->basicPayCentavos;

        // Compute 13th month: Total basic ÷ 12
        if ($totalBasicPayForYear > 0) {
            $ctx->thirteenthMonthCentavos = (int) round(
                $totalBasicPayForYear / 12,
                0,
                PHP_ROUND_HALF_UP,
            );

            // Tax exemption: First ₱90,000 is tax-free (RR 3-2015)
            $exemptionLimit = 90_000_00; // ₱90,000 in centavos
            $ctx->thirteenthMonthTaxableCentavos = max(
                0,
                $ctx->thirteenthMonthCentavos - $exemptionLimit,
            );

            // Add to gross pay for the period (13th month is paid in December)
            $ctx->grossPayCentavos += $ctx->thirteenthMonthCentavos;
            
            // Re-compute taxable income with 13th month
            // Only the excess over ₱90,000 is taxable
            $ctx->taxableIncomeCentavos += $ctx->thirteenthMonthTaxableCentavos;
        }

        return $next($ctx);
    }
}
