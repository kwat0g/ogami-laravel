<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use App\Domains\Payroll\Services\TaxWithholdingService;
use Closure;

/**
 * Step 01 — Employee Rate Snapshots.
 *
 * Captures the employee's current pay rates into the computation context
 * at the start of the pipeline, so that subsequent steps always work with
 * consistent data even if the employee record is updated mid-run.
 *
 * Also determines minimum-wage-earner status (used by tax and loan steps).
 */
final class Step01SnapshotsStep
{
    public function __construct(
        private readonly TaxWithholdingService $tax,
    ) {}

    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $ctx->basicMonthlyCentavos = $ctx->employee->basic_monthly_rate;
        $ctx->dailyRateCentavos = $ctx->employee->daily_rate;
        $ctx->hourlyRateCentavos = $ctx->employee->hourly_rate;
        $ctx->payBasis = $ctx->employee->pay_basis;
        $ctx->isMinimumWageEarner = $this->tax->isMinimumWageEarner(
            $ctx->basicMonthlyCentavos,
            'NCR',
            $ctx->run->cutoff_end,
        );

        return $next($ctx);
    }
}
