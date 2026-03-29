<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * REC-10: Auto-creates fiscal periods to prevent GL posting failures.
 *
 * Without a fiscal period for the current month, ALL journal entry
 * postings fail -- payroll disburse, AP payments, production costs, etc.
 * This command ensures periods are created proactively.
 *
 * Schedule: daily at midnight.
 */
final class FiscalPeriodAutoCreateCommand extends Command
{
    protected $signature = 'accounting:auto-create-fiscal-period
        {--days-ahead=7 : Create next month period this many days before month end}';

    protected $description = 'Ensure fiscal periods exist for current and upcoming months';

    public function handle(FiscalPeriodService $service): int
    {
        $now = Carbon::now();
        $daysAhead = (int) $this->option('days-ahead');
        $issues = [];

        // 1. Check current month has an open period
        $currentPeriod = FiscalPeriod::where('date_from', '<=', $now->toDateString())
            ->where('date_to', '>=', $now->toDateString())
            ->first();

        if (! $currentPeriod) {
            $this->warn('No fiscal period exists for current month. Creating...');
            $period = $this->createMonthPeriod($service, $now);
            if ($period) {
                $this->info("  Created: {$period->name}");
            } else {
                $issues[] = 'Failed to create current month period';
            }
        } elseif ($currentPeriod->status !== 'open') {
            $issues[] = "Current month period '{$currentPeriod->name}' exists but is {$currentPeriod->status}";
            $this->warn("  Current period exists but is {$currentPeriod->status} — GL postings will fail.");
        } else {
            $this->info("Current period OK: {$currentPeriod->name} (open)");
        }

        // 2. If within $daysAhead of month end, create next month period
        $daysUntilMonthEnd = $now->daysInMonth - $now->day;
        if ($daysUntilMonthEnd <= $daysAhead) {
            $nextMonth = $now->copy()->addMonth()->startOfMonth();
            $nextPeriod = FiscalPeriod::where('date_from', '<=', $nextMonth->toDateString())
                ->where('date_to', '>=', $nextMonth->toDateString())
                ->first();

            if (! $nextPeriod) {
                $this->info("Within {$daysAhead} days of month end — creating next month period...");
                $period = $this->createMonthPeriod($service, $nextMonth);
                if ($period) {
                    $this->info("  Created: {$period->name}");
                } else {
                    $issues[] = 'Failed to create next month period';
                }
            } else {
                $this->info("Next month period already exists: {$nextPeriod->name}");
            }
        }

        if (! empty($issues)) {
            Log::warning('Fiscal period auto-create found issues', ['issues' => $issues]);
            $this->error('Issues found: '.implode('; ', $issues));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function createMonthPeriod(FiscalPeriodService $service, Carbon $date): ?FiscalPeriod
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        $name = $startOfMonth->format('F Y');

        try {
            $period = $service->create([
                'name' => $name,
                'date_from' => $startOfMonth->toDateString(),
                'date_to' => $endOfMonth->toDateString(),
                'fiscal_year' => (int) $startOfMonth->format('Y'),
            ]);

            Log::info('Fiscal period auto-created', [
                'period_id' => $period->id,
                'name' => $name,
                'date_from' => $startOfMonth->toDateString(),
                'date_to' => $endOfMonth->toDateString(),
            ]);

            return $period;
        } catch (\Throwable $e) {
            Log::error('Failed to auto-create fiscal period', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            $this->error("  Failed to create period: {$e->getMessage()}");

            return null;
        }
    }
}
