<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PagibigContributionService;
use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;

/**
 * Step 12 — Pag-IBIG (HDMF) Contribution (Employee + Employer).
 *
 * Pag-IBIG contribution is deducted on BOTH cut-offs (semi-monthly split).
 * PAGIBIG-004: Semi-monthly deduction = monthly_contribution / 2.
 * Skipped on zero-gross-pay periods only.
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
        // PAGIBIG-004: Deduct on BOTH cutoffs (semi-monthly), skip only if no gross pay
        if ($ctx->grossPayCentavos === 0) {
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
