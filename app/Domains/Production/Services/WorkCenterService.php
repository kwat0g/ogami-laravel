<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\WorkCenter;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Pagination\LengthAwarePaginator;

final class WorkCenterService implements ServiceContract
{
    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = WorkCenter::query()->orderBy('code');

        if ($filters['search'] ?? null) {
            $v = $filters['search'];
            $query->where(fn ($q) => $q->where('code', 'ilike', "%{$v}%")->orWhere('name', 'ilike', "%{$v}%"));
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data): WorkCenter
    {
        return WorkCenter::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'hourly_rate_centavos' => $data['hourly_rate_centavos'] ?? 0,
            'overhead_rate_centavos' => $data['overhead_rate_centavos'] ?? 0,
            'capacity_hours_per_day' => $data['capacity_hours_per_day'] ?? 8,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function update(WorkCenter $wc, array $data): WorkCenter
    {
        $wc->update(array_filter([
            'code' => $data['code'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'hourly_rate_centavos' => $data['hourly_rate_centavos'] ?? null,
            'overhead_rate_centavos' => $data['overhead_rate_centavos'] ?? null,
            'capacity_hours_per_day' => $data['capacity_hours_per_day'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null));

        return $wc->refresh();
    }

    public function archive(WorkCenter $wc): void
    {
        $wc->delete();
    }
}
