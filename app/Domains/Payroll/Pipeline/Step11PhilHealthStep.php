<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use App\Domains\Payroll\Services\PhilHealthContributionService;
use Closure;

/**
 * Step 11 — PhilHealth Contribution (Employee + Employer).
 *
 * PhilHealth premium is deducted on BOTH cut-offs (semi-monthly split).
 * PHL-004: Semi-monthly deduction = employee_premium / 2 (deducted each cutoff).
 * Skipped on zero-gross-pay periods only.
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
        // PHL-004: Deduct on BOTH cutoffs (semi-monthly), skip only if no gross pay
        if ($ctx->grossPayCentavos === 0) {
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
