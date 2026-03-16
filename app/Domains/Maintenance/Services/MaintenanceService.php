<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Services;

use App\Domains\Inventory\Services\StockService;
use App\Domains\Maintenance\Models\Equipment;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Maintenance\Models\PmSchedule;
use App\Domains\Maintenance\Models\WorkOrderPart;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class MaintenanceService implements ServiceContract
{
    public function __construct(private readonly StockService $stockService) {}

    /** @param array<string,mixed> $params */
    public function paginateEquipment(array $params = []): LengthAwarePaginator
    {
        return Equipment::query()
            ->when($params['with_archived'] ?? false, fn ($q) => $q->withTrashed())
            ->when($params['search'] ?? null, fn ($q, $v) => $q->where('name', 'ilike', "%{$v}%")->orWhere('equipment_code', 'ilike', "%{$v}%"))
            ->when($params['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when(isset($params['is_active']), fn ($q) => $q->where('is_active', filter_var($params['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->with('pmSchedules')
            ->orderBy('name')
            ->paginate((int) ($params['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function storeEquipment(array $data, int $userId): Equipment
    {
        return Equipment::create([...$data, 'created_by_id' => $userId]);
    }

    /** @param array<string,mixed> $data */
    public function updateEquipment(Equipment $equipment, array $data): Equipment
    {
        $equipment->update($data);

        return $equipment;
    }

    /** @param array<string,mixed> $params */
    public function paginateWorkOrders(array $params = []): LengthAwarePaginator
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
    public function storeWorkOrder(array $data, int $userId): MaintenanceWorkOrder
    {
        return MaintenanceWorkOrder::create([...$data, 'created_by_id' => $userId, 'reported_by_id' => $userId]);
    }

    public function startWorkOrder(MaintenanceWorkOrder $mwo): MaintenanceWorkOrder
    {
        if ($mwo->status !== 'open') {
            throw new DomainException('MAINT_WO_NOT_OPEN');
        }
        $mwo->update(['status' => 'in_progress']);

        return $mwo;
    }

    /** @param array<string,mixed> $data */
    public function completeWorkOrder(MaintenanceWorkOrder $mwo, array $data, User $actor): MaintenanceWorkOrder
    {
        if (! in_array($mwo->status, ['open', 'in_progress'], true)) {
            throw new DomainException('MAINT_WO_CANNOT_COMPLETE');
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

            // C1: Issue spare parts from inventory on WO completion.
            $mwo->loadMissing('spareParts');
            foreach ($mwo->spareParts as $part) {
                if ($part->qty_consumed !== null) {
                    continue; // already consumed
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
     * @param  array<string,mixed>  $data
     */
    public function addPart(MaintenanceWorkOrder $mwo, array $data, int $userId): WorkOrderPart
    {
        if ($mwo->status === 'completed') {
            throw new DomainException(
                message: 'Cannot add parts to a completed work order.',
                errorCode: 'MAINT_WO_COMPLETED',
                httpStatus: 422,
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

    /** @param array<string,mixed> $data */
    public function storePmSchedule(Equipment $equipment, array $data): PmSchedule
    {
        return $equipment->pmSchedules()->create($data);
    }
}
