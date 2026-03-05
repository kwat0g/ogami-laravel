<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use App\Domains\Payroll\Services\TaxWithholdingService;
use Closure;

/**
 * Step 13 — Taxable Income.
 *
 * Computes taxable income per TRAIN Law (RR 11-2018):
 *
 *   taxable_income = gross_pay
 *                  − sss_ee
 *                  − philhealth_ee
 *                  − pagibig_ee
 *                  − non_taxable_adjustments   (de minimis, allowances, etc.)
 *
 * Non-taxable earning adjustments are identified by type='earning' + nature='non_taxable'.
 * The actual bracket lookup and cumulative withholding computation happens in Step 14.
 */
final class Step13TaxableIncomeStep
{
    public function __construct(
        private readonly TaxWithholdingService $tax,
    ) {}

    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $nonTaxableAdjustments = (int) $ctx->adjustments
            ->where('type', 'earning')
            ->where('nature', 'non_taxable')
            ->sum('amount_centavos');

        $ctx->taxableIncomeCentavos = $this->tax->computeTaxableIncome(
            $ctx->grossPayCentavos,
            $ctx->sssEeCentavos,
            $ctx->philhealthEeCentavos,
            $ctx->pagibigEeCentavos,
            $nonTaxableAdjustments,
        );

        return $next($ctx);
    }
}
