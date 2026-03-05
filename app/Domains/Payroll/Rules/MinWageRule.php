<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Rules;

use Illuminate\Support\Facades\DB;

/**
 * MinWageRule — evaluates whether an employee's net pay respects the
 * prevailing minimum wage for their region (EDGE-001 / LN-007).
 *
 * If net pay after all standard deductions would fall below the minimum,
 * loan deductions should be deferred (not zeroed) to the next period.
 */
final class MinWageRule
{
    /**
     * Check whether a proposed net pay amount is at or above the semi-monthly
     * minimum wage floor for the given region.
     *
     * @param  int  $netPayCentavos  Proposed net pay for this period
     * @param  string  $region  e.g. 'NCR'
     * @param  string  $asOfDate  Date to look up the prevailing rate (YYYY-MM-DD)
     * @return bool True = net pay is safe (at or above floor), False = below floor
     */
    public function isSafe(int $netPayCentavos, string $region = 'NCR', string $asOfDate = ''): bool
    {
        $floor = $this->semiMonthlyFloorCentavos($region, $asOfDate);

        return $netPayCentavos >= $floor;
    }

    /**
     * The semi-monthly net-pay floor in centavos.
     * = (daily_min_wage × 26 / 2) × 100
     */
    public function semiMonthlyFloorCentavos(string $region = 'NCR', string $asOfDate = ''): int
    {
        if ($asOfDate === '') {
            $asOfDate = now()->toDateString();
        }

        $dailyRatePesos = DB::table('minimum_wage_rates')
            ->where('region', $region)
            ->where('effective_date', '<=', $asOfDate)
            ->orderByDesc('effective_date')
            ->value('daily_rate');

        if ($dailyRatePesos === null) {
            return 0;
        }

        // Monthly = daily × 26; semi-monthly = monthly / 2
        $semiMonthlyPesos = (float) $dailyRatePesos * 26 / 2;

        return (int) round($semiMonthlyPesos * 100, 0, PHP_ROUND_HALF_UP);
    }
}
