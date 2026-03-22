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
 *   - Regular holiday overtime (REGULAR_HOLIDAY_OT multiplier)
 *   - Special holiday overtime (SPECIAL_HOLIDAY_OT multiplier)
 *
 * Multipliers are resolved from the `overtime_multiplier_configs` table.
 * Falls back to 1.0 (no premium) if no config row is found.
 *
 * Formula: (overtime_minutes / 60) × hourly_rate_centavos × multiplier
 */
final class Step06OvertimePayStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $totalOvertimeMinutes = $ctx->overtimeRegularMinutes
            + $ctx->overtimeRestDayMinutes
            + $ctx->overtimeHolidayMinutes;

        if ($totalOvertimeMinutes <= 0) {
            return $next($ctx);
        }

        // Regular day OT
        if ($ctx->overtimeRegularMinutes > 0) {
            $multiplier = PayrollComputationHelper::getOtMultiplier('REGULAR_DAY_OT');
            $ctx->overtimePayCentavos += (int) round(
                ($ctx->overtimeRegularMinutes / 60) * $ctx->hourlyRateCentavos * $multiplier,
                0,
                PHP_ROUND_HALF_UP,
            );
        }

        // Rest day OT
        if ($ctx->overtimeRestDayMinutes > 0) {
            $multiplier = PayrollComputationHelper::getOtMultiplier('REST_DAY_OT');
            $ctx->overtimePayCentavos += (int) round(
                ($ctx->overtimeRestDayMinutes / 60) * $ctx->hourlyRateCentavos * $multiplier,
                0,
                PHP_ROUND_HALF_UP,
            );
        }

        // Holiday OT (both regular and special holidays)
        if ($ctx->overtimeHolidayMinutes > 0) {
            // Use higher multiplier for regular holiday OT if available, else holiday OT
            $multiplier = PayrollComputationHelper::getOtMultiplier('REGULAR_HOLIDAY_OT');
            if ($multiplier === 1.0) {
                $multiplier = PayrollComputationHelper::getOtMultiplier('SPECIAL_HOLIDAY_OT');
            }
            $ctx->overtimePayCentavos += (int) round(
                ($ctx->overtimeHolidayMinutes / 60) * $ctx->hourlyRateCentavos * $multiplier,
                0,
                PHP_ROUND_HALF_UP,
            );
        }

        return $next($ctx);
    }
}
