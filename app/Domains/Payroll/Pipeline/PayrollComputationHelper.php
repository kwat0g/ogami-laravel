<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use Illuminate\Support\Facades\DB;

/**
 * Static utility methods shared across multiple payroll pipeline steps.
 *
 * Deliberately not a service-container binding — it has no I/O dependencies
 * beyond the DB facade and can be called as PayrollComputationHelper::method().
 */
final class PayrollComputationHelper
{
    /**
     * Fetch the current multiplier for an overtime/holiday scenario.
     * Falls back to 1.0 if no configuration is found.
     *
     * @param  string  $scenario  e.g. 'REGULAR_DAY_OT', 'REGULAR_HOLIDAY_WORK'
     */
    public static function getOtMultiplier(string $scenario): float
    {
        $multiplier = DB::table('overtime_multiplier_configs')
            ->where('scenario', $scenario)
            ->orderByDesc('effective_date')
            ->value('multiplier');

        return $multiplier !== null ? (float) $multiplier : 1.0;
    }

    /**
     * Fetch the NCR minimum monthly net floor for minimum-wage protection.
     *
     * Returns the minimum daily rate × 26 working days as centavos.
     * Used by loan-deduction and net-pay steps to protect minimum-wage earners.
     *
     * @param  string  $cutoffEnd  ISO date string for rate lookup ('Y-m-d')
     */
    public static function getMinimumMonthlyNetCentavos(string $cutoffEnd): int
    {
        $minDailyPesos = DB::table('minimum_wage_rates')
            ->where('region', 'NCR')
            ->where('effective_date', '<=', $cutoffEnd)
            ->orderByDesc('effective_date')
            ->value('daily_rate');

        if ($minDailyPesos === null) {
            return 0;
        }

        return (int) round((float) $minDailyPesos * 26 * 100, 0, PHP_ROUND_HALF_UP);
    }
}
