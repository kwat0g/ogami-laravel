<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Events\Procurement\GoodsReceiptQcCompleted;
use App\Events\Procurement\GoodsReceiptSubmittedForQc;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GoodsReceiptService implements ServiceContract
{
    public function __construct(
        private readonly ThreeWayMatchService $threeWayMatchService,
        private readonly GoodsReceiptItemCostSyncService $itemCostSyncService,
    ) {}

    // ── Store (draft) ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function store(PurchaseOrder $po, array $data, array $items, User $actor): GoodsReceipt
    {
        if (! $po->canReceiveGoods()) {
            throw new DomainException(
                message: "Cannot create a Goods Receipt — PO is in status '{$po->status}'.",
                errorCode: 'GR_PO_NOT_SENT',
                httpStatus: 422,
            );
        }

        if (empty($items)) {
            throw new DomainException(
                message: 'A Goods Receipt must have at least one line item.',
                errorCode: 'GR_NO_ITEMS',
                httpStatus: 422,
            );
        }

        $receivedDate = $data['received_date'] ?? now()->toDateString();

        return DB::transaction(function () use ($po, $data, $items, $actor, $receivedDate): GoodsReceipt {
            $reference = $this->generateReference();

            $gr = GoodsReceipt::create([
                'gr_reference' => $reference,
                'purchase_order_id' => $po->id,
                'received_by_id' => $actor->id,
                'received_date' => $receivedDate,
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'condition_notes' => $data['condition_notes'] ?? null,
                'status' => 'draft',
                'three_way_match_passed' => false,
                'ap_invoice_created' => false,
            ]);

            foreach ($items as $item) {
                // Validate quantity against effective pending (accounts for negotiated quantities)
                $poItem = $po->items()->findOrFail($item['po_item_id']);
                $effectivePending = $poItem->effectiveQuantity() - (float) $poItem->quantity_received;
                if ((float) $item['quantity_received'] > $effectivePending) {
                    throw new DomainException(
                        message: "Received quantity ({$item['quantity_received']}) exceeds effective pending ({$effectivePending}) for PO item #{$poItem->id}.",
                        errorCode: 'GR_QTY_EXCEEDS_PENDING',
                        httpStatus: 422,
                    );
                }

                // item_master_id comes directly from the PO item FK — no name-matching needed
                GoodsReceiptItem::create([
                    'goods_receipt_id' => $gr->id,
                    'po_item_id' => $item['po_item_id'],
                    'item_master_id' => $poItem->item_master_id,
                    'quantity_received' => $item['quantity_received'],
                    'unit_of_measure' => $item['unit_of_measure'],
                    'condition' => $item['condition'] ?? 'good',
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            return $gr->refresh();
        });
    }

    // ── Update (draft only) ─────────────────────────────────────────────────

    /**
     * Update a draft GR header (received_date, delivery_note, condition_notes).
     */
    public function update(GoodsReceipt $gr, array $data): GoodsReceipt
    {
        if ($gr->status !== 'draft') {
            throw new DomainException(
                message: "Only draft Goods Receipts can be updated (current: {$gr->status}).",
                errorCode: 'GR_NOT_EDITABLE',
                httpStatus: 422,
            );
        }

        $gr->update(array_intersect_key($data, array_flip([
            'received_date', 'delivery_note_number', 'condition_notes',
        ])));

        return $gr->refresh();
    }

    /**
     * Update a specific GR line item (quantity, condition, remarks).
     * Allows warehouse staff to mark individual items as damaged/rejected.
     */
    public function updateItem(GoodsReceipt $gr, int $itemId, array $data): GoodsReceipt
    {
        if ($gr->status !== 'draft') {
            throw new DomainException(
                message: "Only draft GR items can be updated (current: {$gr->status}).",
                errorCode: 'GR_NOT_EDITABLE',
                httpStatus: 422,
            );
        }

        $grItem = $gr->items()->findOrFail($itemId);

        // Validate quantity against PO pending if changed
        if (isset($data['quantity_received'])) {
            $poItem = $grItem->poItem;
            if ($poItem && (float) $data['quantity_received'] > (float) $poItem->quantity_pending) {
                throw new DomainException(
                    message: "Received quantity ({$data['quantity_received']}) exceeds PO pending ({$poItem->quantity_pending}).",
                    errorCode: 'GR_QTY_EXCEEDS_PENDING',
                    httpStatus: 422,
                );
            }
        }

        // Require remarks for rejected/damaged items
        $condition = $data['condition'] ?? $grItem->condition;
        if (in_array($condition, ['rejected', 'damaged'], true) && empty($data['remarks'] ?? $grItem->remarks)) {
            throw new DomainException(
                message: 'Remarks are required when marking items as rejected or damaged.',
                errorCode: 'GR_CONDITION_NEEDS_REMARKS',
                httpStatus: 422,
            );
        }

        $grItem->update(array_intersect_key($data, array_flip([
            'quantity_received', 'condition', 'remarks',
        ])));

        return $gr->refresh()->load('items');
    }

    // ── Submit for QC ────────────────────────────────────────────────────────

    /**
     * Submit a GR draft for QC inspection — sets status to pending_qc.
     * Used when the GR contains items that require incoming quality control.
     * After QC passes, call confirm() to complete the receipt.
     */
    public function submitForQc(GoodsReceipt $gr, User $actor): GoodsReceipt
    {
        if ($gr->status !== 'draft') {
            throw new DomainException(
                message: "GR must be in draft status to submit for QC (current: {$gr->status}).",
                errorCode: 'GR_NOT_DRAFT',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($gr, $actor): GoodsReceipt {
            $this->resolveItemMasters($gr);

            // Mark all items as pending QC
            $gr->items()->update(['qc_status' => 'pending']);

            $gr->update([
                'status' => 'pending_qc',
                'submitted_for_qc_by_id' => $actor->id,
                'submitted_for_qc_at' => now(),
            ]);

            // Fire event — listener will auto-create IQC inspections and notify QC team
            DB::afterCommit(fn () => GoodsReceiptSubmittedForQc::dispatch($gr->fresh()));

            return $gr->refresh();
        });
    }

    // ── QC Result Transitions ────────────────────────────────────────────────

    /**
     * Mark a GR as QC passed — transitions pending_qc -> qc_passed.
     * Called by the UpdateGrOnInspectionResult listener when all IQC inspections pass.
     */
    public function markQcPassed(GoodsReceipt $gr, User $actor): GoodsReceipt
    {
        if ($gr->status !== 'pending_qc') {
            throw new DomainException(
                message: "GR must be in pending_qc status to mark QC passed (current: {$gr->status}).",
                errorCode: 'GR_NOT_PENDING_QC',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($gr, $actor): GoodsReceipt {
            // Mark all items as QC passed, set accepted qty = received qty
            foreach ($gr->items as $item) {
                $item->update([
                    'qc_status' => 'passed',
                    'quantity_accepted' => $item->quantity_received,
                    'quantity_rejected' => 0,
                ]);
            }

            $gr->update([
                'status' => 'qc_passed',
                'qc_result' => 'passed',
                'qc_completed_at' => now(),
                'qc_completed_by_id' => $actor->id,
            ]);

            DB::afterCommit(fn () => GoodsReceiptQcCompleted::dispatch($gr->fresh(), 'passed'));

            return $gr->refresh();
        });
    }

    /**
     * Mark a GR as QC failed — transitions pending_qc -> qc_failed.
     * Called by the UpdateGrOnInspectionResult listener when any IQC inspection fails.
     */
    public function markQcFailed(GoodsReceipt $gr, User $actor, ?string $notes = null): GoodsReceipt
    {
        if ($gr->status !== 'pending_qc') {
            throw new DomainException(
                message: "GR must be in pending_qc status to mark QC failed (current: {$gr->status}).",
                errorCode: 'GR_NOT_PENDING_QC',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($gr, $actor, $notes): GoodsReceipt {
            $gr->update([
                'status' => 'qc_failed',
                'qc_result' => 'failed',
                'qc_completed_at' => now(),
                'qc_completed_by_id' => $actor->id,
                'qc_notes' => $notes,
            ]);

            DB::afterCommit(fn () => GoodsReceiptQcCompleted::dispatch($gr->fresh(), 'failed'));

            return $gr->refresh();
        });
    }

    // ── Re-submit for QC (re-inspection after failure) ─────────────────────────

    /**
     * Re-submit a QC-failed GR for re-inspection.
     * Transitions qc_failed -> pending_qc, voiding previous inspections.
     * Used after vendor rework or when re-inspection is requested.
     */
    public function resubmitForQc(GoodsReceipt $gr, User $actor): GoodsReceipt
    {
        if ($gr->status !== 'qc_failed') {
            throw new DomainException(
                message: "GR must be in qc_failed status to resubmit for QC (current: {$gr->status}).",
                errorCode: 'GR_NOT_QC_FAILED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($gr, $actor): GoodsReceipt {
            // Void existing failed inspections so new ones can be created
            $gr->inspections()
                ->where('stage', 'iqc')
                ->whereIn('status', ['failed', 'passed'])
                ->each(function ($inspection) {
                    $inspection->update(['status' => 'voided']);
                    $inspection->delete(); // soft-delete
                });

            // Reset item QC statuses
            $gr->items()->update([
                'qc_status' => 'pending',
                'quantity_accepted' => null,
                'quantity_rejected' => null,
            ]);

            $gr->update([
                'status' => 'pending_qc',
                'qc_result' => null,
                'qc_completed_at' => null,
                'qc_completed_by_id' => null,
                'qc_notes' => ($gr->qc_notes ? $gr->qc_notes . "\n" : '') . "[Re-submitted for QC by {$actor->name} at " . now()->toIso8601String() . ']',
                'submitted_for_qc_by_id' => $actor->id,
                'submitted_for_qc_at' => now(),
            ]);

            // Fire event to auto-create new IQC inspections
            DB::afterCommit(fn () => GoodsReceiptSubmittedForQc::dispatch($gr->fresh()));

            return $gr->refresh();
        });
    }

    // ── Accept with Defects (partial acceptance) ─────────────────────────────

    /**
     * Accept a QC-failed GR with defects documented via NCR.
     * Transitions qc_failed -> partial_accept.
     *
     * @param  array{items: list<array{gr_item_id: int, quantity_accepted: float, quantity_rejected: float, defect_type?: string, defect_description?: string, ncr_id?: int}>, notes?: string}  $data
     */
    public function acceptWithDefects(GoodsReceipt $gr, array $data, User $actor): GoodsReceipt
    {
        if ($gr->status !== 'qc_failed') {
            throw new DomainException(
                message: "GR must be in qc_failed status to accept with defects (current: {$gr->status}).",
                errorCode: 'GR_NOT_QC_FAILED',
                httpStatus: 422,
            );
        }

        if (empty($data['items'])) {
            throw new DomainException(
                message: 'At least one item disposition is required for partial acceptance.',
                errorCode: 'GR_NO_ITEM_DISPOSITIONS',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($gr, $data, $actor): GoodsReceipt {
            foreach ($data['items'] as $itemData) {
                $grItem = $gr->items()->findOrFail($itemData['gr_item_id']);

                $qtyAccepted = (float) $itemData['quantity_accepted'];
                $qtyRejected = (float) $itemData['quantity_rejected'];
                $totalQty = (float) $grItem->quantity_received;

                // Validate quantities sum up
                if (abs(($qtyAccepted + $qtyRejected) - $totalQty) > 0.001) {
                    throw new DomainException(
                        message: "Accepted ({$qtyAccepted}) + rejected ({$qtyRejected}) must equal received ({$totalQty}) for item #{$grItem->id}.",
                        errorCode: 'GR_QTY_MISMATCH',
                        httpStatus: 422,
                    );
                }

                // Require defect info for items with rejected quantity
                if ($qtyRejected > 0 && empty($itemData['defect_type'])) {
                    throw new DomainException(
                        message: 'Defect type is required when rejecting any quantity.',
                        errorCode: 'GR_DEFECT_TYPE_REQUIRED',
                        httpStatus: 422,
                    );
                }

                $grItem->update([
                    'qc_status' => $qtyRejected > 0 ? 'accepted_with_ncr' : 'passed',
                    'quantity_accepted' => $qtyAccepted,
                    'quantity_rejected' => $qtyRejected,
                    'defect_type' => $itemData['defect_type'] ?? null,
                    'defect_description' => $itemData['defect_description'] ?? null,
                    'ncr_id' => $itemData['ncr_id'] ?? null,
                ]);
            }

            $gr->update([
                'status' => 'partial_accept',
                'qc_result' => 'partial',
                'qc_notes' => $data['notes'] ?? $gr->qc_notes,
                'qc_completed_by_id' => $actor->id,
                'qc_completed_at' => now(),
            ]);

            DB::afterCommit(fn () => GoodsReceiptQcCompleted::dispatch($gr->fresh(), 'partial'));

            return $gr->refresh()->load('items');
        });
    }

    // ── Confirm ──────────────────────────────────────────────────────────────

    /**
     * Confirm a GR after QC has passed or defects have been accepted.
     * Only allowed from qc_passed or partial_accept status.
     */
    public function confirm(GoodsReceipt $gr, User $actor): GoodsReceipt
    {
        if (! in_array($gr->status, ['qc_passed', 'partial_accept'], true)) {
            throw new DomainException(
                message: "GR must be in qc_passed or partial_accept status to confirm (current: {$gr->status}). Submit for QC first.",
                errorCode: 'GR_NOT_CONFIRMABLE',
                httpStatus: 422,
            );
        }

        // Validate: items with rejected condition must have remarks
        foreach ($gr->items as $item) {
            if ($item->condition === 'rejected' && empty($item->remarks)) {
                throw new DomainException(
                    message: 'Rejected items must have a remarks explanation.',
                    errorCode: 'GR_REJECTED_NEEDS_REMARKS',
                    httpStatus: 422,
                );
            }
        }

        $confirmed = DB::transaction(function () use ($gr, $actor): GoodsReceipt {
            $this->resolveItemMasters($gr);

            $gr->update([
                'status' => 'confirmed',
                'confirmed_by_id' => $actor->id,
                'confirmed_at' => now(),
            ]);

            $this->threeWayMatchService->runMatch($gr->refresh());

            return $gr->refresh();
        });

        // Best-effort sync of material costs from procurement prices.
        // Skip during feature-test transactions to avoid swallowing DB errors
        // that would poison the wrapping transaction state.
        if (! app()->environment('testing')) {
            $this->itemCostSyncService->syncFromGoodsReceipt($confirmed);
        }

        return $confirmed;
    }

    // ── Reject ───────────────────────────────────────────────────────────────

    /**
     * Reject a GR (wrong / damaged goods before confirmation).
     */
    public function reject(GoodsReceipt $gr, User $actor, string $reason): GoodsReceipt
    {
        if (! in_array($gr->status, ['draft', 'pending_qc', 'qc_failed'], true)) {
            throw new DomainException(
                message: "Only draft, pending_qc, or qc_failed GRs can be rejected. Current status: '{$gr->status}'.",
                errorCode: 'GR_NOT_REJECTABLE',
                httpStatus: 422,
            );
        }

        $gr->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'rejected_by_id' => $actor->id,
            'rejected_at' => now(),
        ]);

        return $gr->refresh();
    }

    // ── Return to Supplier (F-016) ──────────────────────────────────────────

    /**
     * Reverse a confirmed GR — return goods to supplier.
     *
     * Creates a reversal record, issues stock OUT via StockService,
     * updates PO received quantities, and posts a reversal JE.
     *
     * @param  array{reason: string, items?: list<array{gr_item_id: int, quantity_returned: float}>}  $data
     *
     * @throws DomainException
     */
    public function returnToSupplier(GoodsReceipt $gr, array $data, User $actor): GoodsReceipt
    {
        if ($gr->status !== 'confirmed') {
            throw new DomainException(
                message: "Only confirmed GRs can be returned to supplier (current: {$gr->status}).",
                errorCode: 'GR_NOT_CONFIRMED',
                httpStatus: 422,
            );
        }

        if ($gr->three_way_match_passed) {
            throw new DomainException(
                message: 'Cannot return goods after a successful three-way match. Create a supplier return adjustment workflow instead.',
                errorCode: 'GR_ALREADY_MATCHED',
                httpStatus: 422,
            );
        }

        // C4 FIX: Idempotency guard — prevent double-return which would
        // double-reverse stock and permanently desync inventory.
        if ($gr->returned_at !== null || $gr->status === 'returned') {
            throw new DomainException(
                message: 'This Goods Receipt has already been returned.',
                errorCode: 'GR_ALREADY_RETURNED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($gr, $data, $actor): GoodsReceipt {
            // C4 FIX: Re-check inside transaction with pessimistic lock to prevent
            // race condition where two concurrent return requests both pass the
            // pre-transaction guard.
            /** @var GoodsReceipt $gr */
            $gr = GoodsReceipt::lockForUpdate()->findOrFail($gr->id);
            if ($gr->status === 'returned' || $gr->returned_at !== null) {
                throw new DomainException(
                    message: 'This Goods Receipt has already been returned (concurrent request detected).',
                    errorCode: 'GR_ALREADY_RETURNED',
                    httpStatus: 409,
                );
            }

            $gr->load(['items.poItem', 'purchaseOrder']);

            // Determine which items to return (all if not specified)
            $itemsToReturn = $data['items'] ?? null;

            // Resolve stock location (same logic as UpdateStockOnThreeWayMatch)
            $locationId = DB::table('warehouse_locations')
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->orderByRaw("CASE WHEN LOWER(name) LIKE '%receiv%' OR LOWER(code) LIKE '%recv%' THEN 0 ELSE 1 END")
                ->value('id');

            $stockService = app(\App\Domains\Inventory\Services\StockService::class);

            foreach ($gr->items as $grItem) {
                // If specific items requested, check if this one is included
                $qtyToReturn = null;
                if ($itemsToReturn !== null) {
                    $match = collect($itemsToReturn)->firstWhere('gr_item_id', $grItem->id);
                    if ($match === null) {
                        continue;
                    }
                    $qtyToReturn = (float) $match['quantity_returned'];
                } else {
                    $qtyToReturn = (float) $grItem->quantity_received;
                }

                if ($qtyToReturn <= 0) {
                    continue;
                }

                // Issue stock OUT (return to supplier)
                if ($grItem->item_master_id && $locationId) {
                    try {
                        $stockService->issue(
                            itemId: $grItem->item_master_id,
                            locationId: $locationId,
                            quantity: $qtyToReturn,
                            referenceType: 'goods_receipt_return',
                            referenceId: $gr->id,
                            actor: $actor,
                            remarks: "Return to supplier — GR {$gr->gr_reference}: {$data['reason']}",
                        );
                    } catch (\Throwable $e) {
                        throw new DomainException(
                            message: "Cannot return item: {$e->getMessage()}",
                            errorCode: 'GR_RETURN_STOCK_ERROR',
                            httpStatus: 422,
                        );
                    }
                }

                // Reverse the PO item received quantity
                $poItem = $grItem->poItem;
                if ($poItem !== null) {
                    $newReceived = max(0, (float) $poItem->quantity_received - $qtyToReturn);
                    $poItem->update(['quantity_received' => $newReceived]);
                }
            }

            // Update GR status
            $gr->update([
                'status' => 'returned',
                'returned_at' => now(),
                'returned_by_id' => $actor->id,
                'return_reason' => $data['reason'],
            ]);

            // Re-open PO if items are now pending again
            $po = $gr->purchaseOrder;
            if ($po && $po->status === 'fully_received') {
                $po->update(['status' => 'partially_received']);
            }

            return $gr->refresh();
        });
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /**
     * For each GR item with null item_master_id, attempt to match an existing
     * ItemMaster by name (case-insensitive), or auto-create one from the PO item.
     */
    private function resolveItemMasters(GoodsReceipt $gr): void
    {
        $fallbackCategoryId = ItemCategory::query()->value('id');

        if ($fallbackCategoryId === null) {
            $fallback = ItemCategory::create([
                'code' => 'AUTO_MISC',
                'name' => 'Auto Misc',
                'description' => 'Auto-created fallback category for uncatalogued GR items.',
                'is_active' => true,
            ]);

            $fallbackCategoryId = $fallback->id;
        }

        foreach ($gr->items as $grItem) {
            if ($grItem->item_master_id !== null) {
                continue;
            }

            $poItem = $grItem->poItem;
            if (! $poItem) {
                continue;
            }

            $name = trim($poItem->item_description);

            // M3 FIX: Normalize item name before matching to prevent duplicate
            // ItemMasters caused by extra spaces or case variants.
            // Collapse multiple spaces, trim, and lowercase for comparison.
            $normalizedName = mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));

            $existing = ItemMaster::whereRaw('LOWER(TRIM(REGEXP_REPLACE(name, \'\\s+\', \' \', \'g\'))) = ?', [$normalizedName])->first();

            if ($existing) {
                $grItem->update(['item_master_id' => $existing->id]);
                $poItem->update(['item_master_id' => $existing->id]);

                continue;
            }

            $slug = strtoupper(substr(Str::slug($name), 0, 20));
            $code = 'AUTO-'.$slug.'-'.strtoupper(Str::random(4));

            $master = ItemMaster::create([
                'item_code' => $code,
                'name' => $name,
                'unit_of_measure' => $poItem->unit_of_measure,
                'type' => 'raw_material',
                'is_active' => true,
                'category_id' => $fallbackCategoryId,
            ]);

            $grItem->update(['item_master_id' => $master->id]);
            $poItem->update(['item_master_id' => $master->id]);
        }
    }

    private function generateReference(): string
    {
        $seq = DB::selectOne('SELECT NEXTVAL(\'goods_receipt_seq\') AS val');
        $num = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);

        return 'GR-'.now()->format('Y-m').'-'.$num;
    }
}
