<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Leave\Services\LeaveAccrualService;
use Illuminate\Console\Command;

/**
 * Year-end leave balance carry-over and renewal command.
 *
 * Uses LeaveAccrualService to process year-end carry-over for all active employees.
 * - Caps balance to max_carry_over_days from leave_types
 * - Creates new year balance records with carried over amount as opening_balance
 *
 * Run via scheduler: 0 2 1 1 * (January 1st at 2 AM)
 * Or run manually: php artisan leave:renew --year=2025
 */
final class RenewLeaveBalances extends Command
{
    protected $signature = 'leave:renew
                            {--year= : Closing year to process (default: previous year)}
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Process year-end carry-over and create new year leave balances';

    public function handle(LeaveAccrualService $service): int
    {
        $closingYear = (int) $this->option('year') ?: (now()->year - 1);
        $openingYear = $closingYear + 1;
        $dryRun = $this->option('dry-run');

        $this->info("Processing year-end carry-over from {$closingYear} to {$openingYear}...");

        if ($dryRun) {
            $this->warn('Dry run mode - no changes will be saved.');

            return self::SUCCESS;
        }

        $service->processYearEndCarryOver($closingYear);

        $this->info("Year-end carry-over completed for {$closingYear} → {$openingYear}.");
        $this->info("New leave balance records created for year {$openingYear}.");

        return self::SUCCESS;
    }
}
