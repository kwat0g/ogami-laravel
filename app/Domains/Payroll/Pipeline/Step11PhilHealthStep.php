<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use App\Domains\Payroll\Services\PhilHealthContributionService;
use Closure;

/**
 * Step 11 — PhilHealth Contribution (Employee + Employer).
 *
 * PhilHealth premium is deducted once per month (2nd cut-off only).
 * Skipped entirely on the 1st cut-off and on zero-gross-pay periods.
 *
 * The ER share mirrors the EE share (50 / 50 split) and is stored for
 * journal-entry and government-remittance reporting.
 */
final class Step11PhilHealthStep
{
    public function __construct(
        private readonly PhilHealthContributionService $philhealth,
    ) {}

    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        if (! $ctx->isSecondCutoff || $ctx->grossPayCentavos === 0) {
            return $next($ctx);
        }

        $ctx->philhealthEeCentavos = $this->philhealth->computeEmployeeSharePerPeriod(
            $ctx->basicMonthlyCentavos,
        );
        $ctx->philhealthErCentavos = $this->philhealth->computeEmployerSharePerPeriod(
            $ctx->basicMonthlyCentavos,
        );

        return $next($ctx);
    }
}
