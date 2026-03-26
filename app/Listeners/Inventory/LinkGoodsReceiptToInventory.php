<?php

declare(strict_types=1);

namespace App\Listeners\Inventory;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Inventory\Services\StockService;
use App\Events\Procurement\ThreeWayMatchPassed;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * When a GR passes three-way match, auto-receive stock into the default
 * warehouse location for each GR line item.
 *
 * Implements ShouldBeUnique so that even if ThreeWayMatchPassed fires twice
 * (e.g. due to after_commit/immediate double-dispatch), only one stock write
 * occurs per GR.
 */
final class LinkGoodsReceiptToInventory implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    /** Lock TTL — how long the unique lock is held while the job is processing. */
    public int $uniqueFor = 60;

    public function __construct(private readonly StockService $stockService) {}

    /** Unique per GR id — prevents duplicate stock writes for the same GR. */
    public function uniqueId(ThreeWayMatchPassed $event): string
    {
        return 'gr-stock-'.$event->goodsReceipt->id;
    }

    public function handle(ThreeWayMatchPassed $event): void
    {
        $gr = $event->goodsReceipt;

        // Load items with poItem for fallback item_master_id resolution
        $gr->load('items.poItem');

        // System actor for auto-receive
        $systemUser = User::where('email', 'admin@ogamierp.local')->first();
        if ($systemUser === null) {
            Log::warning('LinkGoodsReceiptToInventory: no system user found, skipping.');

            return;
        }

        // Use first active location as default receiving location
        $defaultLocation = WarehouseLocation::where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($defaultLocation === null) {
            Log::warning('LinkGoodsReceiptToInventory: no warehouse locations configured, skipping.');

            return;
        }

        foreach ($gr->items as $grItem) {
            // Prefer item_master_id set on the GR item; fall back to resolving
            // from the linked PO item description (e.g. "PP Resin Natural (RAW-001)")
            $itemMasterId = $grItem->item_master_id;

            if ($itemMasterId === null && $grItem->po_item_id !== null) {
                $poItem = $grItem->poItem;
                $desc = $poItem?->item_description ?? '';

                if (preg_match('/\(([A-Z0-9\-]+)\)/', $desc, $m)) {
                    $itemMasterId = ItemMaster::where('item_code', $m[1])->value('id');
                }

                if ($itemMasterId === null) {
                    $itemMasterId = ItemMaster::where('item_code', trim($desc))->value('id');
                }

                if ($itemMasterId === null) {
                    $itemMasterId = ItemMaster::where('name', trim($desc))->value('id');
                }
            }

            if ($itemMasterId === null) {
                Log::info("LinkGoodsReceiptToInventory: skipping GR item #{$grItem->id} — no item_master_id resolved.");

                continue;
            }

            try {
                $this->stockService->receive(
                    itemId: $itemMasterId,
                    locationId: $defaultLocation->id,
                    quantity: (float) $grItem->quantity_received,
                    referenceType: 'goods_receipts',
                    referenceId: $gr->id,
                    actor: $systemUser,
                    lotNumber: 'GR-'.$gr->id.'-L'.$grItem->id,
                    receivedFrom: 'vendor',
                    receivedDate: now()->toDateString(),
                    remarks: 'Auto-received from GR #'.$gr->id,
                );
            } catch (\Throwable $e) {
                Log::error("LinkGoodsReceiptToInventory: failed for item {$itemMasterId}", [
                    'error' => $e->getMessage(),
                    'gr_id' => $gr->id,
                ]);
            }
        }
    }
}
