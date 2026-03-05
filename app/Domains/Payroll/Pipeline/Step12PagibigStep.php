<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PagibigContributionService;
use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;

/**
 * Step 12 — Pag-IBIG (HDMF) Contribution (Employee + Employer).
 *
 * Pag-IBIG contribution is deducted once per month (2nd cut-off only).
 * Skipped on the 1st cut-off and on zero-gross-pay periods.
 *
 * Minimum contribution: ₱200/month (EE + ER combined).
 * Maximum EE contribution rate: 2 % of MBR up to ₱5,000 (handled inside the service).
 */
final class Step12PagibigStep
{
    public function __construct(
        private readonly PagibigContributionService $pagibig,
    ) {}

    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        if (! $ctx->isSecondCutoff || $ctx->grossPayCentavos === 0) {
            return $next($ctx);
        }

        $ctx->pagibigEeCentavos = $this->pagibig->computeEmployeeSharePerPeriod(
            $ctx->basicMonthlyCentavos,
        );
        $ctx->pagibigErCentavos = $this->pagibig->computeEmployerSharePerPeriod(
            $ctx->basicMonthlyCentavos,
        );

        return $next($ctx);
    }
}
