<?php

declare(strict_types=1);

namespace App\Domains\Mold\Services;

use App\Domains\Maintenance\Services\MaintenanceService;
use App\Domains\Mold\Models\MoldMaster;
use App\Domains\Mold\Models\MoldShotLog;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class MoldService implements ServiceContract
{
    public function __construct(private readonly MaintenanceService $maintenanceService) {}

    /** @param array<string,mixed> $params */
    public function paginate(array $params = []): LengthAwarePaginator
    {
        return MoldMaster::query()
            ->when($params['with_archived'] ?? false, fn ($q) => $q->withTrashed())
            ->when($params['search'] ?? null, fn ($q, $v) => $q->where('name', 'ilike', "%{$v}%")->orWhere('mold_code', 'ilike', "%{$v}%"))
            ->when($params['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('name')
            ->paginate((int) ($params['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, int $userId): MoldMaster
    {
        return MoldMaster::create([...$data, 'created_by_id' => $userId]);
    }

    /** @param array<string,mixed> $data */
    public function update(MoldMaster $mold, array $data): MoldMaster
    {
        $mold->update($data);
        return $mold;
    }

    /** @param array<string,mixed> $data */
    public function logShots(MoldMaster $mold, array $data, int $userId): MoldShotLog
    {
        /** @var MoldShotLog $log */
        $log = $mold->shotLogs()->create([
            'shot_count'         => $data['shot_count'],
            'production_order_id' => $data['production_order_id'] ?? null,
            'operator_id'        => $data['operator_id'] ?? $userId,
            'log_date'           => $data['log_date'] ?? today()->toDateString(),
            'remarks'            => $data['remarks'] ?? null,
        ]);

        // MOLD-MAINT-001: After logging shots, refresh the mold to get the updated
        // current_shots (incremented by DB trigger trg_update_mold_shots) then
        // create a Preventive Maintenance WO if the shot limit has been reached.
        $mold->refresh();

        if ($mold->max_shots !== null && $mold->current_shots >= $mold->max_shots) {
            $this->maintenanceService->storeWorkOrder(
                data: [
                    'mold_master_id' => $mold->id,
                    'equipment_id'   => null,
                    'type'           => 'preventive',
                    'priority'       => 'high',
                    'title'          => "Mold PM — {$mold->mold_code} reached shot limit",
                    'description'    => "Mold '{$mold->name}' (code: {$mold->mold_code}) has reached its maximum shot count of {$mold->max_shots}. Preventive maintenance is required before further use.",
                    'status'         => 'open',
                ],
                userId: $userId,
            );
        }

        return $log;
    }
}
