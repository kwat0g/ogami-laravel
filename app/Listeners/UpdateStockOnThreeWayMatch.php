<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\AP\Models\VendorItem;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Services\StockService;
use App\Events\Procurement\ThreeWayMatchPassed;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateStockOnThreeWayMatch
{
    public function __construct(private readonly StockService $stockService) {}

    public function handle(ThreeWayMatchPassed $event): void
    {
        $gr = $event->goodsReceipt;
        $gr->load(['items.poItem', 'purchaseOrder.vendor']);
        $vendor = $gr->purchaseOrder?->vendor;

        // Load the confirming user for audit trail in stock ledger
        $actor = $gr->confirmed_by_id
            ? User::find($gr->confirmed_by_id)
            : null;

        // Resolve stock location once — prefer a "receiving" area, else first active location
        $locationId = DB::table('warehouse_locations')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN LOWER(name) LIKE '%receiv%' OR LOWER(code) LIKE '%recv%' THEN 0 ELSE 1 END")
            ->value('id');

        foreach ($gr->items as $grItem) {
            $poItem = $grItem->poItem;

            if (! $poItem) {
                continue;
            }

            DB::transaction(function () use ($poItem, $grItem, $vendor, $locationId, $gr, $actor): void {
                // Resolve item_master_id if missing
                if (! $poItem->item_master_id) {
                    $resolved = $this->resolveOrCreateItemMaster($poItem, $vendor);
                    if ($resolved !== null) {
                        $poItem->item_master_id = $resolved;
                        $poItem->save();
                    }
                }

                // Update stock only if we have both an item master and a location
                if ($poItem->item_master_id && $locationId && $actor) {
                    $this->stockService->receive(
                        itemId: $poItem->item_master_id,
                        locationId: $locationId,
                        quantity: (float) $grItem->quantity_received,
                        referenceType: 'goods_receipts',
                        referenceId: $gr->id,
                        actor: $actor,
                        receivedFrom: $gr->purchaseOrder->vendor->name ?? 'vendor',
                        receivedDate: (string) $gr->received_date,
                        remarks: "GR #{$gr->gr_reference} three-way match confirmed.",
                    );
                } else {
                    Log::warning('Stock not updated for GR item — missing item_master, warehouse location, or confirming user', [
                        'gr_id'            => $gr->id,
                        'po_item_id'       => $poItem->id,
                        'item_description' => $poItem->item_description,
                        'has_item_master'  => (bool) $poItem->item_master_id,
                        'has_location'     => (bool) $locationId,
                        'has_actor'        => (bool) $actor,
                    ]);
                }
            });
        }
    }

    /**
     * Resolve an existing ItemMaster by name/code, or create one from vendor catalog.
     * Returns null only if auto-creation fails (e.g. no category available).
     */
    private function resolveOrCreateItemMaster($poItem, $vendor): ?int
    {
        // 1. Try to find a vendor item by exact item_name match
        $vendorItem = $vendor
            ? VendorItem::where('vendor_id', $vendor->id)
                ->where('is_active', true)
                ->where('item_name', $poItem->item_description)
                ->first()
            : null;

        // 2. Check for existing ItemMaster by name (do NOT fall back to matching item_code against description)
        $existingByName = ItemMaster::where('name', $poItem->item_description)->first();
        if ($existingByName) {
            Log::info('Resolved existing ItemMaster by name', [
                'po_item_id'     => $poItem->id,
                'item_master_id' => $existingByName->id,
            ]);
            return $existingByName->id;
        }

        // If vendor item found, also check by vendor item code
        if ($vendorItem) {
            $existingByCode = ItemMaster::where('item_code', $vendorItem->item_code)->first();
            if ($existingByCode) {
                Log::info('Resolved existing ItemMaster by vendor item code', [
                    'po_item_id'     => $poItem->id,
                    'item_master_id' => $existingByCode->id,
                ]);
                return $existingByCode->id;
            }
        }

        // 3. Need to auto-create — requires a default category
        $categoryId = $this->getOrCreateMiscCategory();
        if ($categoryId === null) {
            Log::error('Cannot auto-create ItemMaster: no item category exists in the system', [
                'po_item_id'       => $poItem->id,
                'item_description' => $poItem->item_description,
            ]);
            return null;
        }

        // 4a. Auto-create from vendor catalog
        if ($vendorItem) {
            $itemMaster = ItemMaster::create([
                'item_code'       => $this->generateItemCode($vendorItem->item_code),
                'name'            => $vendorItem->item_name,
                'description'     => $vendorItem->description ?? 'Auto-created from vendor catalog via GR. Please review.',
                'unit_of_measure' => $vendorItem->unit_of_measure ?? $poItem->unit_of_measure,
                'category_id'     => $categoryId,
                'type'            => 'raw_material',
                'reorder_point'   => 0,
                'reorder_qty'     => 0,
                'requires_iqc'    => false,
                'is_active'       => true,
            ]);

            Log::info('Auto-created ItemMaster from vendor catalog', [
                'po_item_id'     => $poItem->id,
                'item_master_id' => $itemMaster->id,
                'vendor_item_id' => $vendorItem->id,
            ]);

            return $itemMaster->id;
        }

        // 4b. Auto-create from PO item description (no vendor catalog match)
        $itemMaster = ItemMaster::create([
            'item_code'       => $this->generateItemCode('AUTO'),
            'name'            => $poItem->item_description,
            'description'     => 'Auto-created from GR — no catalog match. Please review and categorize.',
            'unit_of_measure' => $poItem->unit_of_measure,
            'category_id'     => $categoryId,
            'type'            => 'raw_material',
            'reorder_point'   => 0,
            'reorder_qty'     => 0,
            'requires_iqc'    => false,
            'is_active'       => true,
        ]);

        Log::info('Auto-created basic ItemMaster from PO item (no catalog match)', [
            'po_item_id'     => $poItem->id,
            'item_master_id' => $itemMaster->id,
        ]);

        return $itemMaster->id;
    }

    /**
     * Find the "Miscellaneous / Uncategorized" category, or create it if none exists.
     * Returns null only if the DB is completely empty with no categories at all and creation fails.
     */
    private function getOrCreateMiscCategory(): ?int
    {
        // Prefer an explicitly named catch-all category
        $cat = ItemCategory::whereRaw("LOWER(code) IN ('misc','uncategorized','general','other')")
            ->orWhereRaw("LOWER(name) IN ('miscellaneous','uncategorized','general','other')")
            ->first();

        if ($cat) {
            return $cat->id;
        }

        // Fall back to any existing category
        $fallback = ItemCategory::first();
        if ($fallback) {
            return $fallback->id;
        }

        // Last resort: create an "Uncategorized" category
        try {
            $newCat = ItemCategory::create([
                'code'        => 'UNCATEGORIZED',
                'name'        => 'Uncategorized',
                'description' => 'Catch-all category for auto-created items. Please re-categorize.',
                'is_active'   => true,
            ]);
            return $newCat->id;
        } catch (\Throwable $e) {
            Log::error('Failed to create fallback item category', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate a unique item code using an advisory lock to prevent race conditions.
     */
    private function generateItemCode(string $prefix): string
    {
        $prefix = strtoupper(substr(trim($prefix), 0, 10));

        $lockKey = crc32('item_code_gen_' . $prefix);
        DB::statement("SELECT pg_advisory_xact_lock({$lockKey})");

        $count = ItemMaster::withTrashed()
            ->where('item_code', 'like', $prefix . '-%')
            ->count();

        return sprintf('%s-%05d', $prefix, $count + 1);
    }
}
