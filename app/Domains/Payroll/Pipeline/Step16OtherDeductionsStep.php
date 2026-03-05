<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;

/**
 * Step 16 — Other (Non-Loan) Voluntary Deductions (DED-001 slot #8).
 *
 * Aggregates PayrollAdjustment rows where type='deduction'.
 * Each adjustment is individually gated against the min-wage floor
 * (daily_minimum_wage × days_worked) — any adjustment that would breach
 * the floor is skipped this period and flagged as deferred.
 *
 * The adjustments collection was already loaded in Step 09, so no extra query.
 */
final class Step16OtherDeductionsStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $netAfterStatutoryAndLoans = $ctx->grossPayCentavos
            - $ctx->sssEeCentavos
            - $ctx->philhealthEeCentavos
            - $ctx->pagibigEeCentavos
            - $ctx->withholdingTaxCentavos
            - $ctx->loanDeductionsCentavos;

        // DED-001 floor — same formula as Step15
        $minMonthlyNetCentavos = PayrollComputationHelper::getMinimumMonthlyNetCentavos($ctx->run->cutoff_end);
        $daysWorked = max(1, $ctx->daysWorked);
        $floorCentavos = (int) round($minMonthlyNetCentavos * $daysWorked / 26.0, 0, PHP_ROUND_HALF_UP);

        $remainingNet = $netAfterStatutoryAndLoans;

        foreach ($ctx->adjustments->where('type', 'deduction') as $adjustment) {
            $amount = (int) $adjustment->amount_centavos;
            $headroom = $remainingNet - $floorCentavos;

            if ($headroom >= $amount) {
                // Apply full deduction
                $remainingNet -= $amount;
                $ctx->otherDeductionsCentavos += $amount;
            } else {
                // Would breach floor — skip this period
                $ctx->hasDeferredDeductions = true;
            }
        }

        return $next($ctx);
    }
}
