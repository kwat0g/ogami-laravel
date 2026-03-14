<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Step 02 — Period Metadata.
 *
 * Determines:
 *   - Whether this is the 2nd cut-off of the month (drives SSS/PhilHealth/PagIBIG deductions)
 *   - Whether this is a December 2nd cut-off (drives 13th-month + tax reconciliation)
 *   - Number of working days in the period (actual Mon-Fri days minus holidays in cutoff range)
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

        // Calculate actual working days (Mon-Fri minus holidays) in the cutoff period
        $ctx->workingDaysInPeriod = $this->calculateWorkingDays($cutoffStart, $cutoffEnd);

        return $next($ctx);
    }

    /**
     * Calculate actual working days (Monday-Friday) between two dates inclusive,
     * excluding holidays from the holiday_calendars table.
     */
    private function calculateWorkingDays(Carbon $start, Carbon $end): int
    {
        // Fetch holiday dates within the range
        $holidays = DB::table('holiday_calendars')
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('holiday_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        $workingDays = 0;
        $current = $start->copy();

        while ($current <= $end) {
            // 1=Monday, 7=Sunday
            $dayOfWeek = (int) $current->format('N');

            // Count only Monday-Friday (1-5) that are NOT holidays
            if ($dayOfWeek <= 5 && ! in_array($current->toDateString(), $holidays, true)) {
                $workingDays++;
            }

            $current->addDay();
        }

        return $workingDays;
    }
}
