<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;

/**
 * Step 07 — Holiday Pay Premium Differential.
 *
 * Computes the premium portion of holiday pay (the basic pay already covers
 * the regular rate; this step adds the premium differential only):
 *
 *   holiday_premium = (multiplier - 1.0) × daily_rate × holiday_days
 *
 * Types handled:
 *   - Regular holiday days   (REGULAR_HOLIDAY_WORK multiplier, typically 2.0)
 *   - Special non-working holiday days (SPECIAL_HOLIDAY_WORK multiplier, typically 1.30)
 *
 * Multipliers resolved from `overtime_multiplier_configs` table.
 */
final class Step07HolidayPayStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $regularHolidayMultiplier = PayrollComputationHelper::getOtMultiplier('REGULAR_HOLIDAY_WORK');
        $specialHolidayMultiplier = PayrollComputationHelper::getOtMultiplier('SPECIAL_HOLIDAY_WORK');

        if ($ctx->regularHolidayDays > 0) {
            $ctx->holidayPayCentavos += (int) round(
                $ctx->regularHolidayDays * $ctx->dailyRateCentavos * ($regularHolidayMultiplier - 1.0),
                0,
                PHP_ROUND_HALF_UP,
            );
        }

        if ($ctx->specialHolidayDays > 0) {
            $ctx->holidayPayCentavos += (int) round(
                $ctx->specialHolidayDays * $ctx->dailyRateCentavos * ($specialHolidayMultiplier - 1.0),
                0,
                PHP_ROUND_HALF_UP,
            );
        }

        return $next($ctx);
    }
}
