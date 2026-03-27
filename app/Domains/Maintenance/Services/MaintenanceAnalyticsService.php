<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Services;

use App\Domains\Maintenance\Models\Equipment;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Maintenance Analytics Service — MTBF, MTTR, OEE per equipment.
 *
 * MTBF (Mean Time Between Failures) = Total uptime / Number of failures
 * MTTR (Mean Time To Repair) = Total repair time / Number of repairs
 * OEE (Overall Equipment Effectiveness) = Availability x Performance x Quality
 */
final class MaintenanceAnalyticsService implements ServiceContract
{
    /**
     * Compute MTBF and MTTR for a specific equipment.
     *
     * @return array{equipment_id: int, equipment_code: string, mtbf_hours: float, mttr_hours: float, total_failures: int, total_repair_hours: float, availability_pct: float}
     */
    public function equipmentMetrics(Equipment $equipment, ?string $fromDate = null, ?string $toDate = null): array
    {
        $from = $fromDate ? Carbon::parse($fromDate) : Carbon::now()->subYear();
        $to = $toDate ? Carbon::parse($toDate) : Carbon::now();

        // Get completed corrective work orders (failures) for this equipment
        $workOrders = MaintenanceWorkOrder::query()
            ->where('equipment_id', $equipment->id)
            ->where('type', 'corrective')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $totalFailures = $workOrders->count();
        $totalRepairHours = $workOrders->sum('labor_hours');

        // Total calendar hours in the period
        $totalHours = $from->diffInHours($to);

        // MTBF = (Total hours - Total repair hours) / Number of failures
        $mtbf = $totalFailures > 0
            ? round(($totalHours - $totalRepairHours) / $totalFailures, 2)
            : (float) $totalHours;

        // MTTR = Total repair hours / Number of repairs
        $mttr = $totalFailures > 0
            ? round($totalRepairHours / $totalFailures, 2)
            : 0.0;

        // Availability = (Total hours - Downtime) / Total hours * 100
        $availability = $totalHours > 0
            ? round((($totalHours - $totalRepairHours) / $totalHours) * 100, 2)
            : 100.0;

        return [
            'equipment_id' => $equipment->id,
            'equipment_code' => $equipment->equipment_code,
            'equipment_name' => $equipment->name,
            'mtbf_hours' => $mtbf,
            'mttr_hours' => $mttr,
            'total_failures' => $totalFailures,
            'total_repair_hours' => round($totalRepairHours, 2),
            'availability_pct' => $availability,
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
        ];
    }

    /**
     * Get MTBF/MTTR summary for all equipment.
     *
     * @return Collection<int, array>
     */
    public function allEquipmentMetrics(?string $fromDate = null, ?string $toDate = null): Collection
    {
        $equipment = Equipment::where('is_active', true)->get();

        return $equipment->map(fn (Equipment $eq) => $this->equipmentMetrics($eq, $fromDate, $toDate));
    }

    /**
     * Maintenance cost per equipment — aggregates labor + parts cost.
     *
     * @return Collection<int, array{equipment_id: int, equipment_code: string, labor_cost_centavos: int, parts_cost_centavos: int, total_cost_centavos: int, work_order_count: int}>
     */
    public function costPerEquipment(): Collection
    {
        $laborCosts = DB::table('maintenance_work_orders')
            ->select(
                'equipment_id',
                DB::raw('COUNT(*) as work_order_count'),
                DB::raw('COALESCE(SUM(labor_hours), 0) as total_labor_hours')
            )
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->groupBy('equipment_id')
            ->get()
            ->keyBy('equipment_id');

        $partsCosts = DB::table('maintenance_work_order_parts')
            ->join('maintenance_work_orders', 'maintenance_work_order_parts.work_order_id', '=', 'maintenance_work_orders.id')
            ->select(
                'maintenance_work_orders.equipment_id',
                DB::raw('COALESCE(SUM(maintenance_work_order_parts.quantity_used * maintenance_work_order_parts.unit_cost), 0) as total_parts_cost')
            )
            ->where('maintenance_work_orders.status', 'completed')
            ->whereNull('maintenance_work_orders.deleted_at')
            ->groupBy('maintenance_work_orders.equipment_id')
            ->get()
            ->keyBy('equipment_id');

        return Equipment::where('is_active', true)->get()->map(function (Equipment $eq) use ($laborCosts, $partsCosts) {
            $labor = $laborCosts[$eq->id] ?? null;
            $parts = $partsCosts[$eq->id] ?? null;

            // Estimate labor cost at default rate (150/hr = 15000 centavos)
            $laborCostCentavos = (int) round(($labor->total_labor_hours ?? 0) * 15_000);
            $partsCostCentavos = (int) (($parts->total_parts_cost ?? 0) * 100);

            return [
                'equipment_id' => $eq->id,
                'equipment_code' => $eq->equipment_code,
                'equipment_name' => $eq->name,
                'labor_cost_centavos' => $laborCostCentavos,
                'parts_cost_centavos' => $partsCostCentavos,
                'total_cost_centavos' => $laborCostCentavos + $partsCostCentavos,
                'work_order_count' => $labor->work_order_count ?? 0,
            ];
        });
    }
}
