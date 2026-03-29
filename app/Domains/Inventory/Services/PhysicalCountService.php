<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\PhysicalCount;
use App\Domains\Inventory\Models\PhysicalCountItem;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\StateMachines\PhysicalCountStateMachine;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Physical Count Service — manages the full count-to-adjustment workflow.
 *
 * Workflow: draft -> in_progress -> pending_approval -> approved -> adjustments posted
 */
final class PhysicalCountService implements ServiceContract
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly PhysicalCountStateMachine $stateMachine = new PhysicalCountStateMachine(),
    ) {}

    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = PhysicalCount::with(['location', 'createdBy'])
            ->orderByDesc('id');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Create a new physical count with items pre-populated from current stock.
     *
     * @param array<string,mixed> $data
     */
    public function store(array $data, User $actor): PhysicalCount
    {
        return DB::transaction(function () use ($data, $actor): PhysicalCount {
            $count = PhysicalCount::create([
                'reference_number' => $data['reference_number'] ?? 'PC-' . now()->format('Ymd-His'),
                'location_id' => $data['location_id'],
                'status' => 'draft',
                'count_date' => $data['count_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by_id' => $actor->id,
            ]);

            // Pre-populate with current stock balances at this location
            $balances = StockBalance::where('location_id', $data['location_id'])
                ->where('quantity_on_hand', '>', 0)
                ->get();

            foreach ($balances as $balance) {
                PhysicalCountItem::create([
                    'physical_count_id' => $count->id,
                    'item_id' => $balance->item_id,
                    'system_qty' => $balance->quantity_on_hand,
                ]);
            }

            // Also add items specified in request that may have zero stock
            foreach ($data['item_ids'] ?? [] as $itemId) {
                $exists = $count->items()->where('item_id', $itemId)->exists();
                if (! $exists) {
                    PhysicalCountItem::create([
                        'physical_count_id' => $count->id,
                        'item_id' => $itemId,
                        'system_qty' => 0,
                    ]);
                }
            }

            return $count->load('items.item', 'location');
        });
    }

    /**
     * Start counting — transitions draft -> in_progress.
     */
    public function startCounting(PhysicalCount $count): PhysicalCount
    {
        $this->stateMachine->transition($count, 'in_progress');

        $count->save();

        return $count->fresh(['items.item', 'location']) ?? $count;
    }

    /**
     * Record counted quantities for items.
     *
     * @param array<int, array{item_id: int, counted_qty: float, remarks?: string}> $counts
     */
    public function recordCounts(PhysicalCount $count, array $counts): PhysicalCount
    {
        if (! in_array($count->status, ['draft', 'in_progress'], true)) {
            throw new DomainException(
                'Physical count must be in draft or in_progress to record counts.',
                'INV_INVALID_COUNT_STATUS',
                422,
                ['current_status' => $count->status]
            );
        }

        DB::transaction(function () use ($count, $counts): void {
            foreach ($counts as $entry) {
                $item = $count->items()->where('item_id', $entry['item_id'])->first();
                if ($item) {
                    $variance = $entry['counted_qty'] - (float) $item->system_qty;
                    $item->update([
                        'counted_qty' => $entry['counted_qty'],
                        'variance_qty' => $variance,
                        'remarks' => $entry['remarks'] ?? $item->remarks,
                    ]);
                }
            }

            if ($count->status === 'draft') {
                $this->stateMachine->transition($count, 'in_progress');
                $count->save();
            }
        });

        return $count->fresh(['items.item', 'location']) ?? $count;
    }

    /**
     * Submit for approval — transitions in_progress -> pending_approval.
     */
    public function submitForApproval(PhysicalCount $count): PhysicalCount
    {
        // Ensure all items have been counted before allowing transition
        $uncounted = $count->items()->whereNull('counted_qty')->count();
        if ($uncounted > 0) {
            throw new DomainException(
                "{$uncounted} items have not been counted yet.",
                'INV_INCOMPLETE_COUNT',
                422,
                ['uncounted_items' => $uncounted]
            );
        }

        $this->stateMachine->transition($count, 'pending_approval');

        $count->save();

        return $count->fresh(['items.item', 'location']) ?? $count;
    }

    /**
     * Approve count and post stock adjustments for all variance items.
     */
    public function approve(PhysicalCount $count, User $approver): PhysicalCount
    {
        $this->stateMachine->transition($count, 'approved');

        return DB::transaction(function () use ($count, $approver): PhysicalCount {
            // Post adjustments for items with variance
            foreach ($count->items as $item) {
                if ($item->variance_qty !== null && (float) $item->variance_qty !== 0.0) {
                    $this->stockService->adjust(
                        itemId: $item->item_id,
                        locationId: $count->location_id,
                        adjustedQty: (float) $item->counted_qty,
                        actor: $approver,
                        remarks: "Physical count #{$count->reference_number} adjustment"
                    );
                }
            }

            $count->approved_by_id = $approver->id;
            $count->approved_at = now();
            $count->save();

            // REC-19: Post variance to GL after approval
            $this->postVarianceToGl($count, $approver);

            return $count->fresh(['items.item', 'location']) ?? $count;
        });
    }

    /**
     * REC-19: Post physical count variance as a journal entry.
     *
     * Calculates the net variance (counted vs system) in centavos and posts
     * a JE debiting/crediting inventory and variance accounts. Skips silently
     * if GL accounts are not configured (logged as warning).
     */
    private function postVarianceToGl(PhysicalCount $count, User $actor): void
    {
        try {
            $varianceCentavos = 0;

            foreach ($count->items as $item) {
                $diff = ((float) $item->counted_quantity - (float) $item->system_quantity);
                $unitCost = (int) ($item->unit_cost_centavos ?? $item->item?->unit_cost_centavos ?? 0);
                $varianceCentavos += (int) round($diff * $unitCost);
            }

            if ($varianceCentavos === 0) {
                return;
            }

            // Look up GL accounts for inventory variance posting
            $inventoryAccount = \App\Domains\Accounting\Models\ChartOfAccount::where('account_code', 'LIKE', '%inventory%')
                ->where('account_type', 'asset')
                ->first();

            $varianceAccount = \App\Domains\Accounting\Models\ChartOfAccount::where('account_code', 'LIKE', '%variance%')
                ->first();

            if (! $inventoryAccount || ! $varianceAccount) {
                \Illuminate\Support\Facades\Log::warning('Physical count variance GL posting skipped: inventory or variance GL account not configured', [
                    'physical_count_id' => $count->id,
                    'variance_centavos' => $varianceCentavos,
                ]);

                return;
            }

            $absVariance = abs($varianceCentavos) / 100;

            $lines = $varianceCentavos > 0
                ? [
                    ['account_id' => $inventoryAccount->id, 'debit' => $absVariance, 'credit' => null],
                    ['account_id' => $varianceAccount->id, 'debit' => null, 'credit' => $absVariance],
                ]
                : [
                    ['account_id' => $varianceAccount->id, 'debit' => $absVariance, 'credit' => null],
                    ['account_id' => $inventoryAccount->id, 'debit' => null, 'credit' => $absVariance],
                ];

            app(\App\Domains\Accounting\Services\JournalEntryService::class)->create([
                'date' => $count->count_date ?? now()->toDateString(),
                'description' => "Inventory variance adjustment - Physical Count #{$count->reference_number}",
                'source_type' => 'physical_counts',
                'source_id' => $count->id,
                'lines' => $lines,
            ]);
        } catch (\Throwable $e) {
            // Non-fatal — count approval must not fail due to GL posting error
            \Illuminate\Support\Facades\Log::error('Physical count variance GL posting failed', [
                'physical_count_id' => $count->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
