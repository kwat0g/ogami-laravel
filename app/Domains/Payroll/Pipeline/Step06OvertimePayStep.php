<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;

/**
 * Step 06 — Overtime Pay.
 *
 * Computes overtime pay for:
 *   - Regular-day overtime (REGULAR_DAY_OT multiplier)
 *   - Rest-day overtime (REST_DAY_OT multiplier)
 *
 * Both multipliers are resolved from the `overtime_multiplier_configs` table.
 * Falls back to 1.0 (no premium) if no config row is found.
 *
 * Formula: (overtime_minutes / 60) × hourly_rate_centavos × multiplier
 */
final class Step06OvertimePayStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        if ($ctx->overtimeRegularMinutes <= 0 && $ctx->overtimeRestDayMinutes <= 0) {
            return $next($ctx);
        }

        $regularOtMultiplier = PayrollComputationHelper::getOtMultiplier('REGULAR_DAY_OT');
        $restDayOtMultiplier = PayrollComputationHelper::getOtMultiplier('REST_DAY_OT');

        if ($ctx->overtimeRegularMinutes > 0) {
            $ctx->overtimePayCentavos += (int) round(
                ($ctx->overtimeRegularMinutes / 60) * $ctx->hourlyRateCentavos * $regularOtMultiplier,
                0,
                PHP_ROUND_HALF_UP,
            );
        }

        if ($ctx->overtimeRestDayMinutes > 0) {
            $ctx->overtimePayCentavos += (int) round(
                ($ctx->overtimeRestDayMinutes / 60) * $ctx->hourlyRateCentavos * $restDayOtMultiplier,
                0,
                PHP_ROUND_HALF_UP,
            );
        }

        return $next($ctx);
    }
}
