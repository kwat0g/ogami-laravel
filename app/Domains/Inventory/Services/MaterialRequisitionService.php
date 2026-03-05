<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Inventory\Models\MaterialRequisitionItem;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class MaterialRequisitionService implements ServiceContract
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * @param array<string, mixed>       $data
     * @param list<array<string, mixed>> $items
     */
    public function store(array $data, array $items, User $actor): MaterialRequisition
    {
        if (empty($items)) {
            throw new DomainException('A Material Requisition must have at least one item.', 'MRQ_NO_ITEMS', 422);
        }

        return DB::transaction(function () use ($data, $items, $actor): MaterialRequisition {
            $mrq = MaterialRequisition::create([
                'requested_by_id' => $actor->id,
                'department_id'   => $data['department_id'],
                'purpose'         => $data['purpose'],
                'status'          => 'draft',
            ]);

            $this->syncItems($mrq, $items);
            return $mrq->refresh();
        });
    }

    private function assertStatus(MaterialRequisition $mrq, string $expected): void
    {
        if ($mrq->status !== $expected) {
            throw new DomainException("Expected status '{$expected}', got '{$mrq->status}'.", 'MRQ_INVALID_STATUS', 422);
        }
    }

    public function submit(MaterialRequisition $mrq, User $actor): MaterialRequisition
    {
        $this->assertStatus($mrq, 'draft');
        $mrq->update(['status' => 'submitted', 'submitted_by_id' => $actor->id, 'submitted_at' => now()]);
        return $mrq->refresh();
    }

    public function note(MaterialRequisition $mrq, User $actor, ?string $comments): MaterialRequisition
    {
        $this->assertStatus($mrq, 'submitted');
        $mrq->update(['status' => 'noted', 'noted_by_id' => $actor->id, 'noted_at' => now(), 'noted_comments' => $comments]);
        return $mrq->refresh();
    }

    public function check(MaterialRequisition $mrq, User $actor, ?string $comments): MaterialRequisition
    {
        $this->assertStatus($mrq, 'noted');
        $mrq->update(['status' => 'checked', 'checked_by_id' => $actor->id, 'checked_at' => now(), 'checked_comments' => $comments]);
        return $mrq->refresh();
    }

    public function review(MaterialRequisition $mrq, User $actor, ?string $comments): MaterialRequisition
    {
        $this->assertStatus($mrq, 'checked');
        $mrq->update(['status' => 'reviewed', 'reviewed_by_id' => $actor->id, 'reviewed_at' => now(), 'reviewed_comments' => $comments]);
        return $mrq->refresh();
    }

    public function vpApprove(MaterialRequisition $mrq, User $actor, ?string $comments): MaterialRequisition
    {
        $this->assertStatus($mrq, 'reviewed');
        $mrq->update(['status' => 'approved', 'vp_approved_by_id' => $actor->id, 'vp_approved_at' => now(), 'vp_comments' => $comments]);
        return $mrq->refresh();
    }

    public function reject(MaterialRequisition $mrq, User $actor, string $reason): MaterialRequisition
    {
        if (in_array($mrq->status, ['draft', 'cancelled', 'fulfilled', 'rejected'], true)) {
            throw new DomainException('Cannot reject a ' . $mrq->status . ' requisition.', 'MRQ_INVALID_STATUS', 422);
        }
        $mrq->update(['status' => 'rejected', 'rejected_by_id' => $actor->id, 'rejected_at' => now(), 'rejection_reason' => $reason]);
        return $mrq->refresh();
    }

    public function cancel(MaterialRequisition $mrq): MaterialRequisition
    {
        if (! $mrq->isCancellable()) {
            throw new DomainException('Only draft or submitted requisitions can be cancelled.', 'MRQ_NOT_CANCELLABLE', 422);
        }
        $mrq->update(['status' => 'cancelled']);
        return $mrq->refresh();
    }

    /**
     * Fulfill an approved MRQ — issue stock for each line item.
     * Default location is the first active warehouse location.
     */
    public function fulfill(MaterialRequisition $mrq, User $actor, int $defaultLocationId): MaterialRequisition
    {
        $this->assertStatus($mrq, 'approved');

        return DB::transaction(function () use ($mrq, $actor, $defaultLocationId): MaterialRequisition {
            $mrq->load('items');
            foreach ($mrq->items as $line) {
                $this->stockService->issue(
                    itemId: $line->item_id,
                    locationId: $defaultLocationId,
                    quantity: (float) $line->qty_requested,
                    referenceType: 'material_requisitions',
                    referenceId: $mrq->id,
                    actor: $actor,
                );
                $line->update(['qty_issued' => $line->qty_requested]);
            }
            $mrq->update(['status' => 'fulfilled', 'fulfilled_by_id' => $actor->id, 'fulfilled_at' => now()]);
            return $mrq->refresh();
        });
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function syncItems(MaterialRequisition $mrq, array $items): void
    {
        $mrq->items()->delete();
        foreach ($items as $i => $line) {
            MaterialRequisitionItem::create([
                'material_requisition_id' => $mrq->id,
                'item_id'      => $line['item_id'],
                'qty_requested' => $line['qty_requested'],
                'remarks'      => $line['remarks'] ?? null,
                'line_order'   => $i,
            ]);
        }
    }
}
