<?php

declare(strict_types=1);

namespace App\Domains\Mold\Services;

use App\Domains\Mold\Models\MoldMaster;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;

/**
 * Mold Analytics Service — cost amortization and lifecycle reporting.
 */
final class MoldAnalyticsService implements ServiceContract
{
    /**
     * Compute cost-per-shot for a mold.
     *
     * @return array{mold_id: int, mold_code: string, cost_centavos: int, current_shots: int, expected_total_shots: int|null, cost_per_shot_centavos: float, life_remaining_pct: float|null, amortized_cost_centavos: int}
     */
    public function costAmortization(MoldMaster $mold): array
    {
        $costPerShot = $mold->expected_total_shots > 0
            ? round($mold->cost_centavos / $mold->expected_total_shots, 4)
            : 0.0;

        $lifeRemainingPct = null;
        if ($mold->max_shots !== null && $mold->max_shots > 0) {
            $remaining = max(0, $mold->max_shots - $mold->current_shots);
            $lifeRemainingPct = round(($remaining / $mold->max_shots) * 100, 2);
        }

        $amortizedCost = (int) round($costPerShot * $mold->current_shots);

        return [
            'mold_id' => $mold->id,
            'mold_code' => $mold->mold_code,
            'mold_name' => $mold->name,
            'cost_centavos' => $mold->cost_centavos,
            'current_shots' => $mold->current_shots,
            'expected_total_shots' => $mold->expected_total_shots,
            'cost_per_shot_centavos' => $costPerShot,
            'life_remaining_pct' => $lifeRemainingPct,
            'amortized_cost_centavos' => $amortizedCost,
            'remaining_value_centavos' => max(0, $mold->cost_centavos - $amortizedCost),
        ];
    }

    /**
     * Lifecycle dashboard for all molds.
     *
     * @return Collection<int, array>
     */
    public function lifecycleDashboard(): Collection
    {
        return MoldMaster::where('is_active', true)
            ->get()
            ->map(fn (MoldMaster $mold) => $this->costAmortization($mold));
    }
}
