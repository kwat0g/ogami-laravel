<?php

declare(strict_types=1);

namespace App\Domains\Mold\Services;

use App\Domains\Mold\Models\MoldMaster;
use App\Domains\Mold\Models\MoldShotLog;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class MoldService implements ServiceContract
{
    /** @param array<string,mixed> $params */
    public function paginate(array $params = []): LengthAwarePaginator
    {
        return MoldMaster::query()
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

        return $log;
    }
}
