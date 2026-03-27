<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Services;

use App\Domains\Inventory\Services\StockService;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Maintenance\Models\WorkOrderPart;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Work Order Service — manages maintenance work orders and spare part consumption.
 *
 * Decomposed from the monolithic MaintenanceService.
 */
final class WorkOrderService implements ServiceContract
{
    public function __construct(private readonly StockService $stockService) {}

    /** @param array<string,mixed> $params */
    public function paginate(array $params = []): LengthAwarePaginator
    {
        return MaintenanceWorkOrder::query()
            ->when($params['with_archived'] ?? false, fn ($q) => $q->withTrashed())
            ->when($params['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($params['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($params['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($params['equipment_id'] ?? null, fn ($q, $v) => $q->where('equipment_id', $v))
            ->with(['equipment', 'assignedTo'])
            ->orderByDesc('created_at')
            ->paginate((int) ($params['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, int $userId): MaintenanceWorkOrder
    {
        return MaintenanceWorkOrder::create([
            ...$data,
            'created_by_id' => $userId,
            'reported_by_id' => $userId,
        ]);
    }

    public function start(MaintenanceWorkOrder $mwo): MaintenanceWorkOrder
    {
        if ($mwo->status !== 'open') {
            throw new DomainException(
                'Work order must be in open status to start.',
                'MAINT_WO_NOT_OPEN',
                422,
            );
        }

        $mwo->update(['status' => 'in_progress']);

        // Mark equipment as under maintenance
        $mwo->equipment()->update(['status' => 'under_maintenance']);

        return $mwo;
    }

    /** @param array<string,mixed> $data */
    public function complete(MaintenanceWorkOrder $mwo, array $data, User $actor): MaintenanceWorkOrder
    {
        if (! in_array($mwo->status, ['open', 'in_progress'], true)) {
            throw new DomainException(
                'Work order cannot be completed from current status.',
                'MAINT_WO_CANNOT_COMPLETE',
                422,
            );
        }

        return DB::transaction(function () use ($mwo, $data, $actor): MaintenanceWorkOrder {
            $mwo->update([
                'status' => 'completed',
                'completed_at' => ! empty($data['actual_completion_date'])
                    ? Carbon::parse($data['actual_completion_date'])
                    : now(),
                'completion_notes' => $data['completion_notes'],
                'labor_hours' => $data['labor_hours'] ?? null,
            ]);

            // Issue spare parts from inventory
            $mwo->loadMissing('spareParts');
            foreach ($mwo->spareParts as $part) {
                if ($part->qty_consumed !== null) {
                    continue;
                }
                $this->stockService->issue(
                    itemId: $part->item_id,
                    locationId: $part->location_id,
                    quantity: $part->qty_required,
                    referenceType: MaintenanceWorkOrder::class,
                    referenceId: $mwo->id,
                    actor: $actor,
                    remarks: "WO #{$mwo->id} — spare part consumption",
                );
                $part->update(['qty_consumed' => $part->qty_required]);
            }

            // Update equipment status back to operational
            $mwo->equipment()->update(['status' => 'operational']);

            return $mwo->fresh();
        });
    }

    /**
     * Add a spare part requirement to a work order.
     *
     * @param array<string,mixed> $data
     */
    public function addPart(MaintenanceWorkOrder $mwo, array $data, int $userId): WorkOrderPart
    {
        if ($mwo->status === 'completed') {
            throw new DomainException(
                'Cannot add parts to a completed work order.',
                'MAINT_WO_COMPLETED',
                422,
            );
        }

        return WorkOrderPart::create([
            'work_order_id' => $mwo->id,
            'item_id' => $data['item_id'],
            'location_id' => $data['location_id'],
            'qty_required' => $data['qty_required'],
            'remarks' => $data['remarks'] ?? null,
            'added_by_id' => $userId,
        ]);
    }

    /**
     * Get maintenance cost summary per equipment.
     *
     * @return Collection<int, array{equipment_id: int, equipment_name: string, total_labor_hours: float, work_order_count: int}>
     */
    public function costSummaryByEquipment(?int $year = null): Collection
    {
        $query = MaintenanceWorkOrder::query()
            ->where('status', 'completed')
            ->when($year, fn ($q, $y) => $q->whereYear('completed_at', $y))
            ->with('equipment')
            ->selectRaw('equipment_id, count(*) as wo_count, coalesce(sum(labor_hours), 0) as total_hours')
            ->groupBy('equipment_id');

        return $query->get()->map(fn ($row) => [
            'equipment_id' => $row->equipment_id,
            'equipment_name' => $row->equipment?->name ?? '—',
            'total_labor_hours' => (float) $row->total_hours,
            'work_order_count' => (int) $row->wo_count,
        ]);
    }
}
