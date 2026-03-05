<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use App\Domains\Payroll\Services\TaxWithholdingService;
use Closure;

/**
 * Step 14 — TRAIN Law Withholding Tax (Cumulative Method).
 *
 * Uses the year-to-date (YTD) accumulators loaded in Step 04 to compute
 * withholding tax via the TRAIN cumulative method:
 *
 *   period_tax = tax_on(ytd_taxable + this_period_taxable) − ytd_tax_already_withheld
 *
 * Exempt if employee is a minimum-wage earner (Step 01 flag).
 * December 2nd cut-off triggers annual tax reconciliation (over/under-deduction).
 */
final class Step14WithholdingTaxStep
{
    public function __construct(
        private readonly TaxWithholdingService $tax,
    ) {}

    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $ctx->withholdingTaxCentavos = $this->tax->computePeriodWithholding(
            $ctx->taxableIncomeCentavos,
            $ctx->ytdTaxableIncomeCentavos,
            $ctx->ytdTaxWithheldCentavos,
            $ctx->isMinimumWageEarner,
        );

        return $next($ctx);
    }
}
