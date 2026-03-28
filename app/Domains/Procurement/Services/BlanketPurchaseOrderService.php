<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\Procurement\Models\BlanketPurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Blanket Purchase Order Service — Item 31.
 *
 * Manages long-term vendor agreements with committed quantities/prices.
 * Individual POs are created as "releases" against the blanket.
 */
final class BlanketPurchaseOrderService implements ServiceContract
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = BlanketPurchaseOrder::with(['vendor', 'createdBy'])
            ->withCount('releases')
            ->orderByDesc('created_at');

        if (isset($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** @param array<string, mixed> $data */
    public function store(array $data, User $actor): BlanketPurchaseOrder
    {
        $seq = DB::selectOne("SELECT NEXTVAL('blanket_po_seq') AS val");
        $num = str_pad((string) ($seq->val ?? rand(1, 99999)), 5, '0', STR_PAD_LEFT);

        return BlanketPurchaseOrder::create([
            'bpo_reference' => 'BPO-' . now()->format('Y-m') . '-' . $num,
            'vendor_id' => $data['vendor_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'committed_amount_centavos' => $data['committed_amount_centavos'],
            'released_amount_centavos' => 0,
            'status' => 'draft',
            'terms' => $data['terms'] ?? null,
            'created_by_id' => $actor->id,
        ]);
    }

    public function activate(BlanketPurchaseOrder $bpo): BlanketPurchaseOrder
    {
        if ($bpo->status !== 'draft') {
            throw new DomainException('Only draft blanket POs can be activated.', 'PROC_BPO_NOT_DRAFT', 422);
        }

        $bpo->update(['status' => 'active']);

        return $bpo->fresh() ?? $bpo;
    }

    /**
     * Create a release PO against the blanket agreement.
     * Validates that the release amount doesn't exceed the remaining committed amount.
     *
     * @param array<string, mixed> $poData
     */
    public function createRelease(BlanketPurchaseOrder $bpo, array $poData, User $actor): PurchaseOrder
    {
        if ($bpo->status !== 'active') {
            throw new DomainException('Blanket PO must be active to create releases.', 'PROC_BPO_NOT_ACTIVE', 422);
        }

        $releaseAmount = (int) ($poData['total_po_amount'] ?? 0);
        $remaining = $bpo->remainingAmountCentavos();

        if ($releaseAmount > $remaining) {
            throw new DomainException(
                "Release amount exceeds remaining blanket commitment. Remaining: {$remaining} centavos.",
                'PROC_BPO_EXCEEDED',
                422,
                ['remaining_centavos' => $remaining, 'requested_centavos' => $releaseAmount],
            );
        }

        return DB::transaction(function () use ($bpo, $poData, $actor, $releaseAmount): PurchaseOrder {
            $po = PurchaseOrder::create([
                ...$poData,
                'vendor_id' => $bpo->vendor_id,
                'blanket_po_id' => $bpo->id,
                'status' => 'draft',
                'notes' => "Release from Blanket PO {$bpo->bpo_reference}",
                'created_by_id' => $actor->id,
            ]);

            $bpo->increment('released_amount_centavos', $releaseAmount);

            return $po;
        });
    }

    /**
     * PR Consolidation — Item 32.
     *
     * When creating a PO, suggest merging PRs for the same vendor/item
     * into a single PO for better pricing leverage.
     *
     * @return \Illuminate\Support\Collection<int, array{pr_id: int, pr_reference: string, vendor_id: int, items: array}>
     */
    public function suggestConsolidation(int $vendorId): \Illuminate\Support\Collection
    {
        return \App\Domains\Procurement\Models\PurchaseRequest::where('vendor_id', $vendorId)
            ->where('status', 'approved')
            ->whereNull('converted_to_po_id')
            ->with('items')
            ->get()
            ->map(fn ($pr) => [
                'pr_id' => $pr->id,
                'pr_reference' => $pr->pr_reference,
                'vendor_id' => $pr->vendor_id,
                'department' => $pr->department?->name ?? '—',
                'total_estimated_cost' => $pr->total_estimated_cost,
                'item_count' => $pr->items->count(),
                'items' => $pr->items->map(fn ($i) => [
                    'item_description' => $i->item_description,
                    'quantity' => $i->quantity,
                    'estimated_cost' => $i->estimated_cost,
                ])->toArray(),
            ]);
    }
}
