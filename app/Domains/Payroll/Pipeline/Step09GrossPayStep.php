<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Models\PayrollAdjustment;
use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;

/**
 * Step 09 — Taxable Earning Adjustments + Gross Pay.
 *
 * Loads all PayrollAdjustment records for this employee on this run,
 * and sums adjustment-type=earning rows into the gross pay total.
 *
 *   gross_pay = basic + overtime + holiday + night_diff + earning_adjustments
 *
 * The loaded adjustments are stored on the context so subsequent steps
 * (Step 13 — TaxableIncome, Step 16 — OtherDeductions) can reuse them
 * without additional queries.
 */
final class Step09GrossPayStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $ctx->adjustments = PayrollAdjustment::where('payroll_run_id', $ctx->run->id)
            ->where('employee_id', $ctx->employee->id)
            ->get();

        $earningAdjustments = $ctx->adjustments
            ->where('type', 'earning')
            ->sum('amount_centavos');

        $ctx->grossPayCentavos = $ctx->basicPayCentavos
            + $ctx->overtimePayCentavos
            + $ctx->holidayPayCentavos
            + $ctx->nightDiffPayCentavos
            + (int) $earningAdjustments;

        return $next($ctx);
    }
}
