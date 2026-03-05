<?php

declare(strict_types=1);

namespace App\Listeners\Inventory;

use App\Domains\Inventory\Services\StockService;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Events\Procurement\ThreeWayMatchPassed;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * When a GR passes three-way match, auto-receive stock into the default
 * warehouse location for each GR line item.
 */
final class LinkGoodsReceiptToInventory implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public function __construct(private readonly StockService $stockService) {}

    public function handle(ThreeWayMatchPassed $event): void
    {
        $gr = $event->goodsReceipt;

        // Load items with item_master relationship (item may be linked via item_master_id or name)
        $gr->load('items');

        // System actor for auto-receive
        $systemUser = User::where('email', 'admin@ogamierp.local')->first();
        if ($systemUser === null) {
            Log::warning('LinkGoodsReceiptToInventory: no system user found, skipping.');
            return;
        }

        // Use first active location as default receiving location
        $defaultLocation = \App\Domains\Inventory\Models\WarehouseLocation::where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($defaultLocation === null) {
            Log::warning('LinkGoodsReceiptToInventory: no warehouse locations configured, skipping.');
            return;
        }

        foreach ($gr->items as $grItem) {
            // item_master_id must be set on the GR line (Warehouse Head configures this)
            if (! isset($grItem->item_master_id) || $grItem->item_master_id === null) {
                continue;
            }

            try {
                $this->stockService->receive(
                    itemId: $grItem->item_master_id,
                    locationId: $defaultLocation->id,
                    quantity: (float) $grItem->quantity_received,
                    referenceType: 'goods_receipts',
                    referenceId: $gr->id,
                    actor: $systemUser,
                    lotNumber: 'GR-' . $gr->id . '-L' . $grItem->id,
                    receivedFrom: 'vendor',
                    receivedDate: now()->toDateString(),
                    remarks: 'Auto-received from GR #' . $gr->id,
                );
            } catch (\Throwable $e) {
                Log::error("LinkGoodsReceiptToInventory: failed for item {$grItem->item_master_id}", [
                    'error' => $e->getMessage(),
                    'gr_id' => $gr->id,
                ]);
            }
        }
    }
}
