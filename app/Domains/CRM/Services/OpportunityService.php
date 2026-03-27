<?php

declare(strict_types=1);

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Opportunity;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class OpportunityService implements ServiceContract
{
    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Opportunity::with(['customer', 'contact', 'assignedTo'])
            ->orderByDesc('id');

        if (isset($filters['stage'])) {
            $query->where('stage', $filters['stage']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['assigned_to_id'])) {
            $query->where('assigned_to_id', $filters['assigned_to_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, User $actor): Opportunity
    {
        return Opportunity::create([
            'customer_id' => $data['customer_id'],
            'contact_id' => $data['contact_id'] ?? null,
            'title' => $data['title'],
            'expected_value_centavos' => $data['expected_value_centavos'] ?? 0,
            'probability_pct' => $data['probability_pct'] ?? 10,
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'stage' => 'prospecting',
            'assigned_to_id' => $data['assigned_to_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_id' => $actor->id,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function update(Opportunity $opportunity, array $data): Opportunity
    {
        if ($opportunity->isClosed()) {
            throw new DomainException('Cannot update a closed opportunity.', 'CRM_OPPORTUNITY_CLOSED', 422);
        }

        $opportunity->update(array_filter([
            'title' => $data['title'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'expected_value_centavos' => $data['expected_value_centavos'] ?? null,
            'probability_pct' => $data['probability_pct'] ?? null,
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'stage' => $data['stage'] ?? null,
            'assigned_to_id' => $data['assigned_to_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return $opportunity->fresh(['customer', 'contact', 'assignedTo']) ?? $opportunity;
    }

    public function closeWon(Opportunity $opportunity): Opportunity
    {
        if ($opportunity->isClosed()) {
            throw new DomainException('Opportunity is already closed.', 'CRM_OPPORTUNITY_CLOSED', 422);
        }

        $opportunity->update(['stage' => 'closed_won', 'probability_pct' => 100]);

        return $opportunity->fresh() ?? $opportunity;
    }

    public function closeLost(Opportunity $opportunity, string $reason): Opportunity
    {
        if ($opportunity->isClosed()) {
            throw new DomainException('Opportunity is already closed.', 'CRM_OPPORTUNITY_CLOSED', 422);
        }

        $opportunity->update([
            'stage' => 'closed_lost',
            'probability_pct' => 0,
            'loss_reason' => $reason,
        ]);

        return $opportunity->fresh() ?? $opportunity;
    }

    /**
     * Pipeline summary with weighted values by stage.
     *
     * @return Collection<int, array{stage: string, count: int, total_centavos: int, weighted_centavos: int}>
     */
    public function pipelineSummary(): Collection
    {
        return Opportunity::query()
            ->whereNotIn('stage', ['closed_won', 'closed_lost'])
            ->get()
            ->groupBy('stage')
            ->map(fn (Collection $opps, string $stage) => [
                'stage' => $stage,
                'count' => $opps->count(),
                'total_centavos' => $opps->sum('expected_value_centavos'),
                'weighted_centavos' => $opps->sum(fn (Opportunity $o) => $o->weightedValueCentavos()),
            ])
            ->values();
    }
}
