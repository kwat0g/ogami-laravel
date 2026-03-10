<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\FixedAssets\Services\FixedAssetService;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * FA-002 — Depreciate all active fixed assets for the most recent open fiscal period.
 *
 * Designed to run monthly (e.g. on the last working day of each month).
 * Uses the most recent OPEN fiscal period — will not double-run (unique constraint
 * on fixed_asset_depreciation_entries prevents duplicate entries per period).
 */
final class DepreciateFixedAssets extends Command
{
    protected $signature = 'assets:depreciate-monthly
                            {--period-id= : Specific fiscal period ID (defaults to most recent open period)}
                            {--actor-id=1 : User ID to record as the actor (defaults to system user)}';

    protected $description = 'Compute and post monthly depreciation for all active fixed assets';

    public function __construct(private readonly FixedAssetService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $periodId = $this->option('period-id');

        $period = $periodId
            ? FiscalPeriod::find((int) $periodId)
            : FiscalPeriod::where('status', 'open')->latest('date_from')->first();

        if ($period === null) {
            $this->error('No open fiscal period found. Open a fiscal period before running depreciation.');

            return self::FAILURE;
        }

        $actorId = (int) ($this->option('actor-id') ?? 1);
        $actor   = User::find($actorId);

        if ($actor === null) {
            $this->error("User ID {$actorId} not found.");

            return self::FAILURE;
        }

        $this->info("Running depreciation for period: {$period->name}");

        $count = $this->service->depreciateMonth($period, $actor);

        $this->info("Depreciated {$count} asset(s).");

        return self::SUCCESS;
    }
}
