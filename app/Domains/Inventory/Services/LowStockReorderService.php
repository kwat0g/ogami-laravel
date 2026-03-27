<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\AP\Models\Vendor;
use App\Domains\HR\Models\Department;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Procurement\Models\PurchaseRequestItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LowStockReorderService
 *
 * Automatically detects items below their reorder point and creates
 * draft Purchase Requests with suggested quantities.
 *
 * Can be called:
 * - Manually from inventory dashboard
 * - Scheduled via Laravel scheduler (e.g., daily at 6 AM)
 * - Triggered after stock deduction events
 *
 * The PRs are created as 'draft' status and flagged as 'system_generated'
 * so purchasing officers can quickly review and submit them.
 */
final class LowStockReorderService
{
    /**
     * Scan all items with reorder points and detect those below threshold.
     *
     * @return Collection<int, array{item: ItemMaster, balance: StockBalance, deficit: float}>
     */
    public function detectLowStock(): Collection
    {
        // Find stock balances where on-hand is below reorder point
        $lowStockItems = StockBalance::query()
            ->with('item')
            ->whereHas('item', fn ($q) => $q
                ->where('is_active', true)
                ->whereNotNull('reorder_point')
                ->where('reorder_point', '>', 0)
            )
            ->get()
            ->filter(function (StockBalance $balance) {
                $item = $balance->item;
                if (! $item || ! $item->reorder_point) {
                    return false;
                }

                return (float) $balance->quantity_on_hand <= (float) $item->reorder_point;
            })
            ->map(function (StockBalance $balance) {
                $item = $balance->item;
                $onHand = (float) $balance->quantity_on_hand;
                $reorderPoint = (float) $item->reorder_point;

                // Calculate Economic Order Quantity (simplified):
                // Suggest ordering enough to reach 2x the reorder point
                $eoq = max(1, ($reorderPoint * 2) - $onHand);

                return [
                    'item' => $item,
                    'balance' => $balance,
                    'on_hand' => $onHand,
                    'reorder_point' => $reorderPoint,
                    'deficit' => max(0, $reorderPoint - $onHand),
                    'suggested_qty' => $eoq,
                ];
            })
            ->values();

        return $lowStockItems;
    }

    /**
     * Create draft Purchase Requests for low-stock items.
     *
     * Groups items by preferred vendor (from most recent PO) and creates
     * one PR per vendor.
     *
     * @return list<PurchaseRequest> Created PRs
     */
    public function createReorderRequests(int $actorId): array
    {
        $lowStockItems = $this->detectLowStock();

        if ($lowStockItems->isEmpty()) {
            Log::info('[LowStockReorder] No items below reorder point');
            return [];
        }

        // Group items by preferred vendor
        $byVendor = [];
        foreach ($lowStockItems as $entry) {
            $item = $entry['item'];
            $vendorId = $this->findPreferredVendor($item);
            $key = $vendorId ?? 'unknown';

            if (! isset($byVendor[$key])) {
                $byVendor[$key] = [
                    'vendor_id' => $vendorId,
                    'items' => [],
                ];
            }

            $byVendor[$key]['items'][] = $entry;
        }

        // Find the purchasing/warehouse department for PR assignment
        $purchasingDept = Department::where('code', 'PURCH')
            ->orWhere('code', 'WH')
            ->first();

        $departmentId = $purchasingDept?->id ?? Department::first()?->id;

        if (! $departmentId) {
            Log::warning('[LowStockReorder] No department found for PR creation');
            return [];
        }

        return DB::transaction(function () use ($byVendor, $actorId, $departmentId): array {
            $createdPRs = [];

            foreach ($byVendor as $group) {
                $vendorId = $group['vendor_id'];
                $items = $group['items'];

                if (empty($items)) {
                    continue;
                }

                // Check if there's already a pending system-generated PR for this vendor
                $existingPr = PurchaseRequest::where('vendor_id', $vendorId)
                    ->where('status', 'draft')
                    ->where('notes', 'LIKE', '%[Auto-Reorder]%')
                    ->first();

                if ($existingPr) {
                    Log::info('[LowStockReorder] Pending auto-reorder PR already exists', [
                        'pr_id' => $existingPr->id,
                        'vendor_id' => $vendorId,
                    ]);
                    continue;
                }

                $seq = DB::selectOne("SELECT NEXTVAL('purchase_request_seq') AS val");
                $num = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);
                $reference = 'PR-' . now()->format('Y-m') . '-' . $num;

                $itemSummary = implode(', ', array_map(
                    fn ($e) => $e['item']->name . ' (' . $e['suggested_qty'] . ' ' . $e['item']->unit_of_measure . ')',
                    array_slice($items, 0, 3)
                ));
                $more = count($items) > 3 ? ' + ' . (count($items) - 3) . ' more' : '';

                $totalEstimated = 0;

                $pr = PurchaseRequest::create([
                    'pr_reference' => $reference,
                    'department_id' => $departmentId,
                    'requested_by_id' => $actorId,
                    'vendor_id' => $vendorId,
                    'urgency' => 'normal',
                    'justification' => "[Auto-Reorder] Stock below reorder point for: {$itemSummary}{$more}",
                    'notes' => '[Auto-Reorder] System-generated reorder request. Review quantities and submit for approval.',
                    'status' => 'draft',
                    'total_estimated_cost' => 0,
                ]);

                foreach ($items as $entry) {
                    $item = $entry['item'];
                    $qty = $entry['suggested_qty'];

                    // Try to get last purchase price from recent PO
                    $lastUnitPrice = $this->getLastPurchasePrice($item, $vendorId);

                    $lineTotal = $qty * $lastUnitPrice;
                    $totalEstimated += $lineTotal;

                    PurchaseRequestItem::create([
                        'purchase_request_id' => $pr->id,
                        'item_master_id' => $item->id,
                        'vendor_item_id' => null,
                        'item_description' => $item->name,
                        'unit_of_measure' => $item->unit_of_measure,
                        'quantity' => $qty,
                        'estimated_unit_cost' => $lastUnitPrice,
                        'specifications' => "Reorder: on-hand={$entry['on_hand']}, reorder_point={$entry['reorder_point']}",
                    ]);
                }

                // Update total
                $pr->update(['total_estimated_cost' => $totalEstimated]);

                $createdPRs[] = $pr;

                Log::info('[LowStockReorder] Auto-reorder PR created', [
                    'pr_id' => $pr->id,
                    'pr_reference' => $reference,
                    'vendor_id' => $vendorId,
                    'item_count' => count($items),
                    'total_estimated' => $totalEstimated,
                ]);
            }

            return $createdPRs;
        });
    }

    /**
     * Find the preferred vendor for an item based on most recent PO.
     */
    private function findPreferredVendor(ItemMaster $item): ?int
    {
        // Look at the most recent PO that included this item
        $recentPo = PurchaseOrder::whereHas('items', fn ($q) => $q->where('item_master_id', $item->id))
            ->whereNotIn('status', ['cancelled'])
            ->orderByDesc('created_at')
            ->first();

        return $recentPo?->vendor_id;
    }

    /**
     * Get the last purchase price for an item from a vendor.
     */
    private function getLastPurchasePrice(ItemMaster $item, ?int $vendorId): float
    {
        if (! $vendorId) {
            return (float) ($item->standard_cost ?? 0);
        }

        $lastPoItem = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_order_items.item_master_id', $item->id)
            ->where('purchase_orders.vendor_id', $vendorId)
            ->whereNotIn('purchase_orders.status', ['cancelled'])
            ->orderByDesc('purchase_orders.created_at')
            ->select('purchase_order_items.unit_price')
            ->first();

        return (float) ($lastPoItem?->unit_price ?? $item->standard_cost ?? 0);
    }
}
