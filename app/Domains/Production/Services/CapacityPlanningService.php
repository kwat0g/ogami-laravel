<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenter;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Capacity Planning Service — Item 7.
 *
 * Checks whether scheduled production fits within work center capacity.
 * Compares required hours (from routings) against available capacity
 * (work_center.capacity_hours_per_day * working days in period).
 *
 * Flexibility:
 *   - Per work center capacity (hours/day configurable)
 *   - Calendar-based: respects holidays
 *   - Overload detection with percentage thresholds
 *   - Supports what-if scenarios (test adding a new order)
 */
final class CapacityPlanningService implements ServiceContract
{
    /**
     * Get capacity utilization for all work centers in a date range.
     *
     * @return Collection<int, array{work_center_id: int, code: string, name: string, capacity_hours: float, required_hours: float, utilization_pct: float, status: string, overloaded_days: list<string>}>
     */
    public function utilizationReport(?string $from = null, ?string $to = null): Collection
    {
        $from = $from ? Carbon::parse($from) : now()->startOfWeek();
        $to = $to ? Carbon::parse($to) : now()->endOfWeek()->addWeeks(3);
        $workingDays = $this->countWorkingDays($from, $to);

        $workCenters = WorkCenter::where('is_active', true)->get();

        return $workCenters->map(function (WorkCenter $wc) use ($from, $to, $workingDays) {
            $capacityHours = $wc->capacity_hours_per_day * $workingDays;

            // Sum required hours from all active production orders using this work center
            $requiredHours = $this->computeRequiredHours($wc->id, $from, $to);

            $utilization = $capacityHours > 0 ? round(($requiredHours / $capacityHours) * 100, 2) : 0.0;

            $status = match (true) {
                $utilization > 100 => 'overloaded',
                $utilization >= 85 => 'near_capacity',
                $utilization >= 50 => 'moderate',
                default => 'available',
            };

            return [
                'work_center_id' => $wc->id,
                'code' => $wc->code,
                'name' => $wc->name,
                'capacity_hours_per_day' => $wc->capacity_hours_per_day,
                'working_days' => $workingDays,
                'total_capacity_hours' => round($capacityHours, 2),
                'required_hours' => round($requiredHours, 2),
                'available_hours' => round(max(0, $capacityHours - $requiredHours), 2),
                'utilization_pct' => $utilization,
                'status' => $status,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
            ];
        })->sortByDesc('utilization_pct')->values();
    }

    /**
     * Check if a production order can be scheduled without overloading.
     *
     * @return array{feasible: bool, work_center_loads: list<array>, bottleneck: string|null}
     */
    public function checkFeasibility(ProductionOrder $order): array
    {
        $order->loadMissing('bom');
        if ($order->bom === null) {
            return ['feasible' => true, 'work_center_loads' => [], 'bottleneck' => null];
        }

        $routings = Routing::where('bom_id', $order->bom_id)
            ->with('workCenter')
            ->orderBy('sequence')
            ->get();

        if ($routings->isEmpty()) {
            return ['feasible' => true, 'work_center_loads' => [], 'bottleneck' => null];
        }

        $qty = (float) $order->qty_required;
        $startDate = $order->target_start_date ? Carbon::parse($order->target_start_date) : now();
        $endDate = $order->target_end_date ? Carbon::parse($order->target_end_date) : $startDate->copy()->addDays(14);
        $workingDays = max(1, $this->countWorkingDays($startDate, $endDate));

        $loads = [];
        $bottleneck = null;
        $feasible = true;

        foreach ($routings as $routing) {
            $wc = $routing->workCenter;
            if ($wc === null) {
                continue;
            }

            $setupHours = (float) $routing->setup_time_hours;
            $runHours = (float) $routing->run_time_hours_per_unit * $qty;
            $totalNeeded = $setupHours + $runHours;

            $capacityAvailable = $wc->capacity_hours_per_day * $workingDays;
            $existingLoad = $this->computeRequiredHours($wc->id, $startDate, $endDate);
            $afterAddition = $existingLoad + $totalNeeded;
            $utilization = $capacityAvailable > 0 ? round(($afterAddition / $capacityAvailable) * 100, 2) : 999;

            if ($utilization > 100) {
                $feasible = false;
                $bottleneck ??= "{$wc->code} ({$wc->name})";
            }

            $loads[] = [
                'work_center_code' => $wc->code,
                'work_center_name' => $wc->name,
                'operation' => $routing->operation_name,
                'hours_needed' => round($totalNeeded, 2),
                'existing_load_hours' => round($existingLoad, 2),
                'capacity_hours' => round($capacityAvailable, 2),
                'projected_utilization_pct' => $utilization,
                'overloaded' => $utilization > 100,
            ];
        }

        return [
            'feasible' => $feasible,
            'work_center_loads' => $loads,
            'bottleneck' => $bottleneck,
        ];
    }

    /**
     * Compute required hours at a work center from active production orders.
     */
    private function computeRequiredHours(int $workCenterId, Carbon $from, Carbon $to): float
    {
        // Get all active production orders in the date range
        $orders = ProductionOrder::query()
            ->whereIn('status', ['draft', 'scheduled', 'released', 'in_progress'])
            ->where(function ($q) use ($from, $to): void {
                $q->whereBetween('target_start_date', [$from, $to])
                    ->orWhereBetween('target_end_date', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to): void {
                        $q2->where('target_start_date', '<=', $from)
                            ->where('target_end_date', '>=', $to);
                    });
            })
            ->whereNotNull('bom_id')
            ->get();

        $totalHours = 0.0;

        foreach ($orders as $order) {
            $routings = Routing::where('bom_id', $order->bom_id)
                ->where('work_center_id', $workCenterId)
                ->get();

            $qty = (float) $order->qty_required;

            foreach ($routings as $routing) {
                $totalHours += (float) $routing->setup_time_hours + ((float) $routing->run_time_hours_per_unit * $qty);
            }
        }

        return $totalHours;
    }

    /**
     * Count working days (Mon-Fri) in a date range, excluding holidays.
     */
    private function countWorkingDays(Carbon $from, Carbon $to): int
    {
        $days = 0;
        $current = $from->copy();

        // Get holidays in the range (gracefully handle missing table)
        $holidays = [];
        try {
            $holidays = DB::table('holidays')
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->pluck('date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->toArray();
        } catch (\Throwable) {
            // holidays table may not exist yet — proceed without holiday exclusion
        }

        while ($current->lte($to)) {
            if ($current->isWeekday() && ! in_array($current->toDateString(), $holidays, true)) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }
}
