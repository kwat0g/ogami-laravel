<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Inventory\Models\MaterialRequisitionItem;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Models\ProductionOrder;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\SodViolationException;
use Illuminate\Support\Facades\DB;

final class MaterialRequisitionService implements ServiceContract
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function store(array $data, array $items, User $actor): MaterialRequisition
    {
        if (empty($items)) {
            throw new DomainException('A Material Requisition must have at least one item.', 'MRQ_NO_ITEMS', 422);
        }

        $itemIds = array_column($items, 'item_id');
        $fgItems = ItemMaster::whereIn('id', $itemIds)->where('type', 'finished_good')->pluck('name');
        if ($fgItems->isNotEmpty()) {
            throw new DomainException(
                message: 'Finished goods cannot be requested via Material Requisition: '.implode(', ', $fgItems->all()),
                errorCode: 'MRQ_FINISHED_GOOD_NOT_ALLOWED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($data, $items, $actor): MaterialRequisition {
            $mrq = MaterialRequisition::create([
                'requested_by_id' => $actor->id,
                'department_id' => $data['department_id'],
                'purpose' => $data['purpose'],
                'status' => 'draft',
            ]);

            $this->syncItems($mrq, $items);

            return $mrq->refresh();
        });
    }

    /**
     * PROD-002: Auto-generate a draft MRQ from a BOM when a production order is released.
     *
     * mr_reference is auto-populated by the PostgreSQL trigger trg_mrq_reference.
     */
    public function createFromBom(ProductionOrder $order, User $actor): MaterialRequisition
    {
        /** @var \App\Domains\Production\Models\BillOfMaterials $bom */
        $bom = $order->bom()->with('components')->firstOrFail();

        $items = $bom->components
            ->map(fn (BomComponent $c): array => [
                'item_id' => $c->component_item_id,
                'qty_requested' => round(
                    (float) $c->qty_per_unit * (float) $order->qty_required * (1 + (float) $c->scrap_factor_pct / 100),
                    4
                ),
                'remarks' => 'Auto from BOM: WO '.$order->po_reference,
            ])
            ->values()
            ->all();

        if (empty($items)) {
            throw new DomainException('BOM has no components; cannot create MRQ.', 'BOM_NO_COMPONENTS', 422);
        }

        return DB::transaction(function () use ($order, $actor, $items): MaterialRequisition {
            // mr_reference intentionally omitted — PostgreSQL trigger trg_mrq_reference fills it
            $mrq = MaterialRequisition::create([
                'requested_by_id' => $actor->id,
                'department_id' => null,
                'production_order_id' => $order->id,
                'purpose' => 'Auto MRQ for WO '.$order->po_reference,
                'status' => 'draft',
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

    public function submit(MaterialRequisition $mrq, User $actor, ?string $stockOverrideReason = null): MaterialRequisition
    {
        $this->assertStatus($mrq, 'draft');
        $mrq->update([
            'status'          => 'submitted',
            'submitted_by_id' => $actor->id,
            'submitted_at'    => now(),
            // Store the override reason so every approver can see why stock was short at submission
            'remarks'         => $stockOverrideReason
                ? '[Stock override] '.$stockOverrideReason
                : $mrq->remarks,
        ]);

        return $mrq->refresh();
    }

    public function note(MaterialRequisition $mrq, User $actor, ?string $comments): MaterialRequisition
    {
        $this->assertStatus($mrq, 'submitted');

        // SoD: the person who submitted cannot be the one who notes it
        if (! $actor->hasRole('super_admin') && (int) $mrq->submitted_by_id === (int) $actor->id) {
            throw new SodViolationException('material_requisition', 'note');
        }

        $mrq->update(['status' => 'noted', 'noted_by_id' => $actor->id, 'noted_at' => now(), 'noted_comments' => $comments]);

        return $mrq->refresh();
    }

    public function check(MaterialRequisition $mrq, User $actor, ?string $comments): MaterialRequisition
    {
        $this->assertStatus($mrq, 'noted');

        // SoD: the person who noted cannot be the one who checks it
        if (! $actor->hasRole('super_admin') && (int) $mrq->noted_by_id === (int) $actor->id) {
            throw new SodViolationException('material_requisition', 'check');
        }

        $mrq->update(['status' => 'checked', 'checked_by_id' => $actor->id, 'checked_at' => now(), 'checked_comments' => $comments]);

        return $mrq->refresh();
    }

    public function review(MaterialRequisition $mrq, User $actor, ?string $comments): MaterialRequisition
    {
        $this->assertStatus($mrq, 'checked');

        // SoD: the person who checked cannot be the one who reviews it
        if (! $actor->hasRole('super_admin') && (int) $mrq->checked_by_id === (int) $actor->id) {
            throw new SodViolationException('material_requisition', 'review');
        }

        $mrq->update(['status' => 'reviewed', 'reviewed_by_id' => $actor->id, 'reviewed_at' => now(), 'reviewed_comments' => $comments]);

        return $mrq->refresh();
    }

    public function vpApprove(MaterialRequisition $mrq, User $actor, ?string $comments): MaterialRequisition
    {
        $this->assertStatus($mrq, 'reviewed');

        // SoD: the person who reviewed cannot be the one who VP-approves it
        if (! $actor->hasRole('super_admin') && (int) $mrq->reviewed_by_id === (int) $actor->id) {
            throw new SodViolationException('material_requisition', 'vp_approve');
        }

        $mrq->update(['status' => 'approved', 'vp_approved_by_id' => $actor->id, 'vp_approved_at' => now(), 'vp_comments' => $comments]);

        return $mrq->refresh();
    }

    public function reject(MaterialRequisition $mrq, User $actor, string $reason): MaterialRequisition
    {
        if (in_array($mrq->status, ['draft', 'cancelled', 'fulfilled', 'rejected'], true)) {
            throw new DomainException('Cannot reject a '.$mrq->status.' requisition.', 'MRQ_INVALID_STATUS', 422);
        }
        $mrq->update(['status' => 'rejected', 'rejected_by_id' => $actor->id, 'rejected_at' => now(), 'rejection_reason' => $reason]);

        // PROD-003: When an auto-MRQ tied to a released WO is rejected, revert the WO to
        // draft so the planner can review and re-release (which generates a new auto-MRQ).
        if ($mrq->production_order_id) {
            \App\Domains\Production\Models\ProductionOrder::query()
                ->where('id', $mrq->production_order_id)
                ->where('status', 'released')
                ->update(['status' => 'draft']);
        }

        return $mrq->refresh();
    }

    public function cancel(MaterialRequisition $mrq): MaterialRequisition
    {
        if (! $mrq->isCancellable()) {
            throw new DomainException('Only draft or submitted requisitions can be cancelled.', 'MRQ_NOT_CANCELLABLE', 422);
        }
        $mrq->update(['status' => 'cancelled']);

        // PROD-003: Mirror reject() — when a linked WO is released and this MRQ is cancelled,
        // revert the WO back to draft so the planner can re-release with a corrected MRQ.
        if ($mrq->production_order_id) {
            \App\Domains\Production\Models\ProductionOrder::query()
                ->where('id', $mrq->production_order_id)
                ->where('status', 'released')
                ->update(['status' => 'draft']);
        }

        return $mrq->refresh();
    }

    /**
     * Fulfill an approved MRQ — issue stock for each line item.
     * Default location is the first active warehouse location.
     */
    public function fulfill(MaterialRequisition $mrq, User $actor, int $defaultLocationId): MaterialRequisition
    {
        $this->assertStatus($mrq, 'approved');

        $mrq->load('items.item');

        // Pre-check ALL items before opening the transaction so the user sees every
        // out-of-stock item at once rather than discovering them one by one.
        $insufficient = [];
        foreach ($mrq->items as $line) {
            $balance = $this->stockService->currentBalance($line->item_id, $defaultLocationId);
            if ($balance < (float) $line->qty_requested) {
                $insufficient[] = $line->item->name ?? "Item #{$line->item_id}";
            }
        }

        if (! empty($insufficient)) {
            $list = implode(', ', $insufficient);
            throw new DomainException(
                count($insufficient) === 1
                    ? "{$insufficient[0]} is out of stock."
                    : "Out of stock: {$list}.",
                'INV_INSUFFICIENT_STOCK',
                422
            );
        }

        return DB::transaction(function () use ($mrq, $actor, $defaultLocationId): MaterialRequisition {
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
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(MaterialRequisition $mrq, array $items): void
    {
        $mrq->items()->delete();
        foreach ($items as $i => $line) {
            MaterialRequisitionItem::create([
                'material_requisition_id' => $mrq->id,
                'item_id' => $line['item_id'],
                'qty_requested' => $line['qty_requested'],
                'remarks' => $line['remarks'] ?? null,
                'line_order' => $i,
            ]);
        }
    }
}
