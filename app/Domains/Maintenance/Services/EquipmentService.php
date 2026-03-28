<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Services;

use App\Domains\Maintenance\Models\Equipment;
use App\Domains\Maintenance\Models\PmSchedule;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Traits\HasArchiveOperations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Equipment Service — manages equipment master data and PM schedules.
 *
 * Decomposed from the monolithic MaintenanceService.
 */
final class EquipmentService implements ServiceContract
{
    use HasArchiveOperations;
    /** @param array<string,mixed> $params */
    public function paginate(array $params = []): LengthAwarePaginator
    {
        return Equipment::query()
            ->when($params['with_archived'] ?? false, fn ($q) => $q->withTrashed())
            ->when(
                $params['search'] ?? null,
                fn ($q, $v) => $q->where('name', 'ilike', "%{$v}%")
                    ->orWhere('equipment_code', 'ilike', "%{$v}%"),
            )
            ->when($params['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when(
                isset($params['is_active']),
                fn ($q) => $q->where('is_active', filter_var($params['is_active'], FILTER_VALIDATE_BOOLEAN)),
            )
            ->with('pmSchedules')
            ->orderBy('name')
            ->paginate((int) ($params['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, int $userId): Equipment
    {
        return Equipment::create([...$data, 'created_by_id' => $userId]);
    }

    /** @param array<string,mixed> $data */
    public function update(Equipment $equipment, array $data): Equipment
    {
        $equipment->update($data);

        return $equipment;
    }

    // ── Archive / Restore / Force Delete ────────────────────────────────────

    public function archive(Equipment $equipment, User $user): void
    {
        $this->archiveRecord($equipment, $user);
    }

    public function restore(int $id, User $user): Equipment
    {
        /** @var Equipment */
        return $this->restoreRecord(Equipment::class, $id, $user);
    }

    public function forceDelete(int $id, User $user): void
    {
        $this->forceDeleteRecord(Equipment::class, $id, $user);
    }

    public function listArchived(int $perPage = 20, ?string $search = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->listArchivedRecords(Equipment::class, $perPage, $search, ['name', 'equipment_code']);
    }

    protected function dependentRelationships(Model $model): array
    {
        return ['workOrders' => 'Work Orders'];
    }

    /**
     * Get equipment with upcoming PM due dates.
     *
     * @return Collection<int, Equipment>
     */
    public function withOverduePm(int $daysAhead = 7): Collection
    {
        return Equipment::query()
            ->where('is_active', true)
            ->whereHas('pmSchedules', function ($q) use ($daysAhead) {
                $q->where('next_due_date', '<=', now()->addDays($daysAhead));
            })
            ->with(['pmSchedules' => function ($q) use ($daysAhead) {
                $q->where('next_due_date', '<=', now()->addDays($daysAhead));
            }])
            ->get();
    }

    /**
     * Get equipment downtime statistics.
     *
     * @return array{total_equipment: int, operational: int, under_maintenance: int, decommissioned: int}
     */
    public function statusSummary(): array
    {
        $counts = Equipment::query()
            ->where('is_active', true)
            ->selectRaw("status, count(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return [
            'total_equipment' => array_sum($counts),
            'operational' => $counts['operational'] ?? 0,
            'under_maintenance' => $counts['under_maintenance'] ?? 0,
            'decommissioned' => $counts['decommissioned'] ?? 0,
        ];
    }

    // ── PM Schedule ────────────────────────────────────────────────────────

    /** @param array<string,mixed> $data */
    public function storePmSchedule(Equipment $equipment, array $data): PmSchedule
    {
        return $equipment->pmSchedules()->create($data);
    }

    /** @param array<string,mixed> $data */
    public function updatePmSchedule(PmSchedule $schedule, array $data): PmSchedule
    {
        $schedule->update($data);

        return $schedule;
    }

    /**
     * Get all PM schedules that are overdue.
     *
     * @return Collection<int, PmSchedule>
     */
    public function overduePmSchedules(): Collection
    {
        return PmSchedule::query()
            ->where('next_due_date', '<', now())
            ->where('is_active', true)
            ->with('equipment')
            ->orderBy('next_due_date')
            ->get();
    }
}
