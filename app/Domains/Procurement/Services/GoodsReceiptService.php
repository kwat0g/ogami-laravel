<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
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
        private readonly \App\Domains\AP\Services\InvoiceAutoDraftService $invoiceAutoDraftService,
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
                // Validate quantity against PO pending amount
                $poItem = $po->items()->findOrFail($item['po_item_id']);
                if ((float) $item['quantity_received'] > (float) $poItem->quantity_pending) {
                    throw new DomainException(
                        message: "Received quantity ({$item['quantity_received']}) exceeds pending quantity ({$poItem->quantity_pending}) for PO item #{$poItem->id}.",
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

    // ── Confirm ──────────────────────────────────────────────────────────────

    /**
     * Confirm a GR draft → triggers three-way match + AP invoice creation.
     */
    public function confirm(GoodsReceipt $gr, User $actor): GoodsReceipt
    {
        if ($gr->status !== 'draft') {
            throw new DomainException(
                message: "GR is already in status '{$gr->status}'.",
                errorCode: 'GR_NOT_DRAFT',
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

            // Auto-draft AP invoice from GR + PO data (creates as 'draft' status)
            try {
                $this->invoiceAutoDraftService->createFromGoodsReceipt($gr->refresh());
            } catch (\Throwable $e) {
                // Don't fail the GR confirmation if auto-draft fails — log and continue
                \Illuminate\Support\Facades\Log::warning('[GR Confirm] AP invoice auto-draft failed', [
                    'gr_id' => $gr->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $gr->refresh();
        });
    }

    // ── Reject ───────────────────────────────────────────────────────────────

    /**
     * Reject a draft GR (wrong / damaged goods before confirmation).
     */
    public function reject(GoodsReceipt $gr, User $actor, string $reason): GoodsReceipt
    {
        if ($gr->status !== 'draft') {
            throw new DomainException(
                message: "Only draft GRs can be rejected. Current status: '{$gr->status}'.",
                errorCode: 'GR_NOT_DRAFT',
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
                    $item->update(['standard_price_centavos' => $agreedCostCentavos]);
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
