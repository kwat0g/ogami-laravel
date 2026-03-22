<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use App\Domains\Payroll\Services\SssContributionService;
use Closure;

/**
 * Step 10 — SSS Contribution (Employee + Employer).
 *
 * SSS is deducted once per month, on the 2nd pay period (2nd cutoff) ONLY.
 * SSS-003: If run = 1st cutoff → 0; if run = 2nd cutoff → full monthly contribution.
 * Zero contribution is applied if there is no gross pay (no-pay, no-contribution).
 *
 * Both EE and ER shares are computed and stored; ER share is for reporting
 * (journal-entry generation) and does not reduce net pay.
 */
final class Step10SssStep
{
    public function __construct(
        private readonly SssContributionService $sss,
    ) {}

    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        if ($ctx->grossPayCentavos === 0) {
            return $next($ctx);
        }

        $ctx->sssEeCentavos = $this->sss->computeEmployeeShare(
            $ctx->basicMonthlyCentavos,
            $ctx->isSecondCutoff,
        );
        $ctx->sssErCentavos = $this->sss->computeEmployerShare(
            $ctx->basicMonthlyCentavos,
            $ctx->isSecondCutoff,
        );

        return $next($ctx);
    }
}
