<?php

declare(strict_types=1);

namespace App\Jobs\Leave;

use App\Domains\Leave\Services\LeaveAccrualService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Monthly leave accrual batch job.
 *
 * Runs on the 1st of each month at 01:00 AM (scheduled via routes/console.php).
 * Credits leave balances for all active employees per LV-002.
 *
 * The job is self-contained: it determines the target month from `now()` so
 * it can safely be re-dispatched manually if a run is missed.
 */
final class RunLeaveAccrualJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600; // 10 minutes

    public function __construct(
        /** Target year — defaults to current year when dispatched. */
        public readonly int $year,
        /** Target month (1–12) — defaults to current month when dispatched. */
        public readonly int $month,
    ) {}

    public function handle(LeaveAccrualService $service): void
    {
        Log::info('RunLeaveAccrualJob started', [
            'year' => $this->year,
            'month' => $this->month,
        ]);

        $result = $service->accrueMonthlyForAll($this->year, $this->month);

        Log::info('RunLeaveAccrualJob completed', [
            'year' => $this->year,
            'month' => $this->month,
            'processed' => $result['processed'],
            'skipped' => $result['skipped'],
        ]);
    }

    /**
     * Human-readable display name for Horizon / queue dashboard.
     */
    public function displayName(): string
    {
        return sprintf(
            'LeaveAccrual-%04d-%02d',
            $this->year,
            $this->month,
        );
    }
}
