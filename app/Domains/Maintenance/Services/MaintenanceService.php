<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Services;

use App\Domains\Maintenance\Models\Equipment;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Maintenance\Models\PmSchedule;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class MaintenanceService implements ServiceContract
{
    /** @param array<string,mixed> $params */
    public function paginateEquipment(array $params = []): LengthAwarePaginator
    {
        return Equipment::query()
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

    public function completeWorkOrder(MaintenanceWorkOrder $mwo, string $notes): MaintenanceWorkOrder
    {
        if (!in_array($mwo->status, ['open', 'in_progress'], true)) {
            throw new DomainException('MAINT_WO_CANNOT_COMPLETE');
        }
        $mwo->update([
            'status'           => 'completed',
            'completed_at'     => now(),
            'completion_notes' => $notes,
        ]);
        // Update equipment status back to operational
        $mwo->equipment()->update(['status' => 'operational']);
        return $mwo;
    }

    /** @param array<string,mixed> $data */
    public function storePmSchedule(Equipment $equipment, array $data): PmSchedule
    {
        return $equipment->pmSchedules()->create($data);
    }
}
