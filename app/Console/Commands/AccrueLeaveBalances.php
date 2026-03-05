<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Leave\Services\LeaveAccrualService;
use Illuminate\Console\Command;

/**
 * Monthly leave balance accrual command.
 *
 * Uses LeaveAccrualService to credit monthly accrual days for all active employees.
 * Run via scheduler: 0 1 1 * * (1st of every month at 1 AM)
 */
final class AccrueLeaveBalances extends Command
{
    protected $signature = 'leave:accrue
                            {--year= : Target year (default: current year)}
                            {--month= : Target month (default: current month)}
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Accrue monthly leave days for active employees (VL, SL)';

    public function handle(LeaveAccrualService $service): int
    {
        $year = (int) $this->option('year') ?: now()->year;
        $month = (int) $this->option('month') ?: now()->month;
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run mode - no changes will be saved.');
            $this->info("Would accrue leave for {$year}-{$month}");

            return self::SUCCESS;
        }

        $this->info("Processing monthly accrual for {$year}-{$month}...");

        $result = $service->accrueMonthlyForAll($year, $month);

        $this->info("Completed: {$result['processed']} processed, {$result['skipped']} skipped.");

        return self::SUCCESS;
    }
}
