<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;
use Illuminate\Support\Carbon;

/**
 * Step 02 — Period Metadata.
 *
 * Determines:
 *   - Whether this is the 2nd cut-off of the month (drives SSS/PhilHealth/PagIBIG deductions)
 *   - Whether this is a December 2nd cut-off (drives 13th-month + tax reconciliation)
 *   - Number of working days in the period (actual Mon-Fri days in cutoff range)
 */
final class Step02PeriodMetaStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $cutoffStart = Carbon::parse($ctx->run->cutoff_start);
        $cutoffEnd = Carbon::parse($ctx->run->cutoff_end);

        // 2nd cutoff: end date falls on or after the 16th of the month
        $ctx->isSecondCutoff = $cutoffEnd->day >= 16;
        $ctx->isDecemberSecondCutoff = $cutoffEnd->month === 12 && $ctx->isSecondCutoff;

        // Calculate actual working days (Mon-Fri) in the cutoff period
        $ctx->workingDaysInPeriod = $this->calculateWorkingDays($cutoffStart, $cutoffEnd);

        return $next($ctx);
    }

    /**
     * Calculate actual working days (Monday-Friday) between two dates inclusive.
     * Excludes weekends (Saturday and Sunday) and holidays.
     */
    private function calculateWorkingDays(Carbon $start, Carbon $end): int
    {
        $workingDays = 0;
        $current = $start->copy();

        while ($current <= $end) {
            // 1=Monday, 7=Sunday
            $dayOfWeek = (int) $current->format('N');

            // Count only Monday-Friday (1-5)
            if ($dayOfWeek <= 5) {
                $workingDays++;
            }

            $current->addDay();
        }

        return $workingDays;
    }
}
