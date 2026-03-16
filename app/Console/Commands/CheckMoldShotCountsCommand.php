<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Mold\Models\MoldMaster;
use Illuminate\Console\Command;

/**
 * Checks mold shot counts against maintenance thresholds
 * and generates alerts (work orders) when thresholds are reached.
 * Designed to run daily via scheduler.
 */
final class CheckMoldShotCountsCommand extends Command
{
    protected $signature = 'mold:check-shot-counts {--threshold=80 : Alert when shot count reaches this percentage of max shots}';

    protected $description = 'Check mold shot counts and generate maintenance alerts';

    public function handle(): int
    {
        $thresholdPct = (int) $this->option('threshold');

        $molds = MoldMaster::where('is_active', true)
            ->whereNotNull('max_shots')
            ->where('max_shots', '>', 0)
            ->get();

        $alerts = 0;

        foreach ($molds as $mold) {
            $currentShots = (int) $mold->current_shots;
            $maxShots = (int) $mold->max_shots;
            $pct = ($currentShots / $maxShots) * 100;

            if ($pct < $thresholdPct) {
                continue;
            }

            // Check if alert work order already exists
            $existing = MaintenanceWorkOrder::where('mold_master_id', $mold->id)
                ->where('type', 'preventive')
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->exists();

            if ($existing) {
                continue;
            }

            MaintenanceWorkOrder::create([
                'equipment_id' => null, // Mold maintenance doesn't require equipment
                'mold_master_id' => $mold->id,
                'type' => 'preventive',
                'priority' => $pct >= 100 ? 'critical' : 'high',
                'status' => 'open',
                'title' => "Mold Maintenance Alert: {$mold->mold_code}",
                'description' => sprintf(
                    'Mold %s has reached %s%% of max shot count (%s / %s shots). %s.',
                    $mold->mold_code,
                    number_format($pct, 1),
                    number_format($currentShots),
                    number_format($maxShots),
                    $pct >= 100 ? 'EXCEEDED — immediate maintenance required' : 'Maintenance recommended soon',
                ),
                'scheduled_date' => now(),
                'created_by_id' => 1,
            ]);

            $alerts++;
        }

        $this->info("Generated {$alerts} mold maintenance alerts from {$molds->count()} active molds.");

        return self::SUCCESS;
    }
}
