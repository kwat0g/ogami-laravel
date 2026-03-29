<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Events\Inventory\ItemPriceChanged;
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

    // ── Confirm ──────────────────────────────────────────────────────────────

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

            $gr->update([
                'status' => 'pending_qc',
                'submitted_for_qc_by_id' => $actor->id,
                'submitted_for_qc_at' => now(),
            ]);

            return $gr->refresh();
        });
    }

    public function confirm(GoodsReceipt $gr, User $actor): GoodsReceipt
    {
        if (! in_array($gr->status, ['draft', 'pending_qc'], true)) {
            throw new DomainException(
                message: "GR must be in draft or pending_qc status to confirm (current: {$gr->status}).",
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

        return DB::transaction(function () use ($gr, $actor): GoodsReceipt {
            $this->resolveItemMasters($gr);

            // ── IQC Gate Enforcement ───────────────────────────────────────────
            // QC-GR-001: Items flagged requires_iqc must have a passed IQC
            // inspection before GR can be confirmed. Provisional receipt is
            // allowed when system_settings 'qc.allow_provisional_receipt' is true
            // (stock enters quarantine with 24hr inspection deadline).
            $this->enforceIqcGate($gr);

            $gr->update([
                'status' => 'confirmed',
                'confirmed_by_id' => $actor->id,
                'confirmed_at' => now(),
            ]);

            // Auto-update item standard prices from PO agreed costs.
            // When materials are purchased from vendors, the agreed unit cost
            // becomes the item's standard price (used in BOM cost calculations).
            $this->updateItemPricesFromPO($gr);

            $this->threeWayMatchService->runMatch($gr->refresh());

            // NOTE: AP invoice auto-drafting is handled by the
            // CreateApInvoiceOnThreeWayMatch event listener triggered from
            // ThreeWayMatchService. Do NOT call invoiceAutoDraftService here
            // to avoid creating duplicate invoices.

            return $gr->refresh();
        });
    }

    // ── Reject ───────────────────────────────────────────────────────────────

    /**
     * Reject a draft GR (wrong / damaged goods before confirmation).
     */
    public function reject(GoodsReceipt $gr, User $actor, string $reason): GoodsReceipt
    {
        if (! in_array($gr->status, ['draft', 'pending_qc'], true)) {
            throw new DomainException(
                message: "Only draft or pending_qc GRs can be rejected. Current status: '{$gr->status}'.",
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

        if ($gr->returned_at !== null) {
            throw new DomainException(
                message: 'This Goods Receipt has already been returned.',
                errorCode: 'GR_ALREADY_RETURNED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($gr, $data, $actor): GoodsReceipt {
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

        foreach ($gr->items as $grItem) {
            if ($grItem->item_master_id !== null) {
                continue;
            }

            $poItem = $grItem->poItem;
            if (! $poItem) {
                continue;
            }

            $name = trim($poItem->item_description);

            $existing = ItemMaster::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

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

    /**
     * IQC Gate Enforcement — QC-GR-001.
     *
     * Checks each GR line item against ItemMaster.requires_iqc. If any item
     * requires incoming quality control, verifies that a passed IQC inspection
     * exists for this GR before allowing confirmation.
     *
     * Flexibility:
     *   - Items with requires_iqc=false skip the check entirely.
     *   - system_setting 'qc.allow_provisional_receipt' (default false):
     *     When true, allows confirmation with a warning instead of blocking,
     *     creating a 24hr inspection deadline. The stock is placed in quarantine.
     *   - Vendor-qualified bypass: if vendor score quality >= 95, skip IQC
     *     (controlled by 'qc.vendor_qualified_threshold', default 95).
     */
    private function enforceIqcGate(GoodsReceipt $gr): void
    {
        $gr->loadMissing(['items.itemMaster', 'purchaseOrder.vendor']);

        $itemsRequiringIqc = [];

        foreach ($gr->items as $grItem) {
            $item = $grItem->itemMaster ?? ($grItem->item_master_id ? ItemMaster::find($grItem->item_master_id) : null);
            if ($item === null || ! $item->requires_iqc) {
                continue;
            }

            $itemsRequiringIqc[] = [
                'gr_item_id' => $grItem->id,
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->name,
            ];
        }

        if (empty($itemsRequiringIqc)) {
            return; // No items require IQC — proceed normally
        }

        // Check vendor-qualified bypass
        $vendor = $gr->purchaseOrder?->vendor ?? null;
        if ($vendor !== null) {
            $qualityThreshold = (float) (DB::table('system_settings')
                ->where('key', 'qc.vendor_qualified_threshold')
                ->value('value') ?? 95);

            // Check vendor quality score — if above threshold, bypass IQC
            try {
                $scoringService = app(\App\Domains\Procurement\Services\VendorScoringService::class);
                $scorecard = $scoringService->scorecard($vendor);
                if ($scorecard['quality_score'] >= $qualityThreshold) {
                    return; // Vendor is qualified — skip IQC
                }
            } catch (\Throwable) {
                // If scoring fails, fall through to normal IQC enforcement
            }
        }

        // Check if passed IQC inspections exist for each item
        $failedItems = [];
        foreach ($itemsRequiringIqc as $iqcItem) {
            $passedInspection = DB::table('inspections')
                ->where('goods_receipt_id', $gr->id)
                ->where('stage', 'iqc')
                ->where('status', 'passed')
                ->where(function ($q) use ($iqcItem): void {
                    $q->where('item_id', $iqcItem['item_id'])
                        ->orWhereNull('item_id'); // GR-level inspection covers all items
                })
                ->exists();

            if (! $passedInspection) {
                $failedItems[] = $iqcItem;
            }
        }

        if (empty($failedItems)) {
            return; // All IQC items have passed inspections
        }

        // Check provisional receipt setting
        $allowProvisional = (bool) (DB::table('system_settings')
            ->where('key', 'qc.allow_provisional_receipt')
            ->value('value') ?? false);

        if ($allowProvisional) {
            // Allow confirmation but log warning — items enter quarantine
            \Illuminate\Support\Facades\Log::warning('[IQC Gate] Provisional receipt — items confirmed without IQC', [
                'gr_id' => $gr->id,
                'items_pending_iqc' => $failedItems,
                'deadline' => now()->addHours(24)->toIso8601String(),
            ]);

            return; // Allow through with warning
        }

        // Hard block — IQC must pass before confirmation
        $itemNames = collect($failedItems)->pluck('item_name')->implode(', ');

        throw new DomainException(
            message: "IQC inspection required but not passed for: {$itemNames}. Create and complete an IQC inspection before confirming this Goods Receipt.",
            errorCode: 'GR_IQC_NOT_PASSED',
            httpStatus: 422,
            context: ['items_pending_iqc' => $failedItems],
        );
    }

    /**
     * Auto-update item standard prices from the PO's agreed unit costs.
     *
     * When a Goods Receipt is confirmed, the vendor's agreed price (from the
     * Purchase Order line) becomes the item's standard_price_centavos. This
     * ensures BOM cost calculations always reflect actual purchase prices.
     *
     * For items using weighted_average costing, delegates to CostingMethodService
     * which computes the new weighted average. For standard costing items, directly
     * updates the standard_price_centavos from the PO agreed cost.
     */
    private function updateItemPricesFromPO(GoodsReceipt $gr): void
    {
        try {
            $gr->loadMissing(['items.poItem', 'purchaseOrder.items']);

            foreach ($gr->items as $grItem) {
                $itemId = $grItem->item_master_id;
                if ($itemId === null) {
                    continue;
                }

                // Get the PO line's agreed unit cost (in pesos, need to convert to centavos)
                $poItem = $grItem->poItem;
                if ($poItem === null) {
                    continue;
                }

                $agreedCostPesos = (float) ($poItem->agreed_unit_cost ?? 0);
                if ($agreedCostPesos <= 0) {
                    continue;
                }

                $agreedCostCentavos = (int) round($agreedCostPesos * 100);

                $item = ItemMaster::find($itemId);
                if ($item === null) {
                    continue;
                }

                // For weighted_average items, use CostingMethodService
                if (($item->costing_method ?? 'standard') === 'weighted_average') {
                    try {
                        $costingService = app(\App\Domains\Inventory\Services\CostingMethodService::class);
                        $costingService->recalculateOnReceipt(
                            $itemId,
                            (float) ($grItem->quantity_received ?? 0),
                            $agreedCostCentavos,
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('[GR] Weighted avg recalc failed', [
                            'item_id' => $itemId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    // Standard costing: update directly from PO price
                    $oldPrice = (int) ($item->standard_price_centavos ?? 0);
                    if ($oldPrice !== $agreedCostCentavos) {
                        $item->update(['standard_price_centavos' => $agreedCostCentavos]);
                        // Fire event so BOM costs are auto-rolled up
                        event(new ItemPriceChanged($itemId, $oldPrice, $agreedCostCentavos, 'goods_receipt'));
                    }
                }
            }
        } catch (\Throwable $e) {
            // Don't fail GR confirmation if price update fails
            \Illuminate\Support\Facades\Log::warning('[GR] Item price auto-update failed', [
                'gr_id' => $gr->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateReference(): string
    {
        $seq = DB::selectOne('SELECT NEXTVAL(\'goods_receipt_seq\') AS val');
        $num = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);

        return 'GR-'.now()->format('Y-m').'-'.$num;
    }
}
