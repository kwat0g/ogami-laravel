<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Routing;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class RoutingService implements ServiceContract
{
    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Routing::with('workCenter', 'bom.productItem')->orderBy('bom_id')->orderBy('sequence');

        if (isset($filters['bom_id'])) {
            $query->where('bom_id', $filters['bom_id']);
        }

        if (isset($filters['work_center_id'])) {
            $query->where('work_center_id', $filters['work_center_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Get all routing steps for a specific BOM.
     */
    public function forBom(int $bomId): Collection
    {
        return Routing::with('workCenter')
            ->where('bom_id', $bomId)
            ->orderBy('sequence')
            ->get();
    }

    /** @param array<string,mixed> $data */
    public function store(array $data): Routing
    {
        // Auto-set sequence if not provided
        if (! isset($data['sequence'])) {
            $maxSeq = Routing::where('bom_id', $data['bom_id'])->max('sequence') ?? 0;
            $data['sequence'] = $maxSeq + 10;
        }

        return Routing::create([
            'bom_id' => $data['bom_id'],
            'work_center_id' => $data['work_center_id'],
            'sequence' => $data['sequence'],
            'operation_name' => $data['operation_name'],
            'description' => $data['description'] ?? null,
            'setup_time_hours' => $data['setup_time_hours'] ?? 0,
            'run_time_hours_per_unit' => $data['run_time_hours_per_unit'] ?? 0,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function update(Routing $routing, array $data): Routing
    {
        $routing->update(array_filter([
            'work_center_id' => $data['work_center_id'] ?? null,
            'sequence' => $data['sequence'] ?? null,
            'operation_name' => $data['operation_name'] ?? null,
            'description' => $data['description'] ?? null,
            'setup_time_hours' => $data['setup_time_hours'] ?? null,
            'run_time_hours_per_unit' => $data['run_time_hours_per_unit'] ?? null,
        ], fn ($v) => $v !== null));

        return $routing->refresh()->load('workCenter');
    }

    public function destroy(Routing $routing): void
    {
        $routing->delete();
    }

    /**
     * Reorder routing steps for a BOM.
     *
     * @param array<int, int> $orderedIds [routing_id => new_sequence]
     */
    public function reorder(int $bomId, array $orderedIds): Collection
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $routingId => $sequence) {
                Routing::where('id', $routingId)->update(['sequence' => $sequence]);
            }
        });

        return $this->forBom($bomId);
    }
}
