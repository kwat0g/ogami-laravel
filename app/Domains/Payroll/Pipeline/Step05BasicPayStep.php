<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;

/**
 * Step 05 — Basic Pay.
 *
 * Pro-rates the employee's semi-monthly salary based on:
 *   billable_days = days_worked + paid_leave_days
 *   basic_pay = (billable_days / working_days_in_period) × semi_monthly_rate
 *
 * Rounding: ROUND_HALF_UP at each division boundary to comply with DOLE
 * centavo-rounding conventions.
 */
final class Step05BasicPayStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $billableDays = $ctx->daysWorked + $ctx->leaveDaysPaid;

        $semiMonthlyRateCentavos = (int) round(
            $ctx->basicMonthlyCentavos / 2,
            0,
            PHP_ROUND_HALF_UP,
        );

        if ($ctx->workingDaysInPeriod > 0) {
            $ctx->basicPayCentavos = (int) round(
                ($billableDays / $ctx->workingDaysInPeriod) * $semiMonthlyRateCentavos,
                0,
                PHP_ROUND_HALF_UP,
            );
        }

        // GAP-2: Deduct proportional pay for tardiness and undertime
        // Formula: (minutes / 60) × hourly_rate_centavos
        if ($ctx->daysLateMinutes > 0) {
            $ctx->lateDeductionCentavos = (int) round(
                ($ctx->daysLateMinutes / 60) * $ctx->hourlyRateCentavos,
                0,
                PHP_ROUND_HALF_UP,
            );
        }

        if ($ctx->undertimeMinutes > 0) {
            $ctx->undertimeDeductionCentavos = (int) round(
                ($ctx->undertimeMinutes / 60) * $ctx->hourlyRateCentavos,
                0,
                PHP_ROUND_HALF_UP,
            );
        }

        $totalLateUtDeduction = $ctx->lateDeductionCentavos + $ctx->undertimeDeductionCentavos;
        $ctx->basicPayCentavos = max(0, $ctx->basicPayCentavos - $totalLateUtDeduction);

        return $next($ctx);
    }
}
