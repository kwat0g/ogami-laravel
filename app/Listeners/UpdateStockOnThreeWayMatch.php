<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Procurement\ThreeWayMatchPassed;
use App\Domains\Inventory\Models\StockBalance;
use Illuminate\Support\Facades\DB;

class UpdateStockOnThreeWayMatch
{
    public function handle(ThreeWayMatchPassed $event): void
    {
        $gr = $event->goodsReceipt;
        $gr->load(['items.poItem']);

        foreach ($gr->items as $grItem) {
            $poItem = $grItem->poItem;
            
            if ($poItem && $poItem->item_master_id) {
                // Find a warehouse location (default to first available)
                $locationId = DB::table('warehouse_locations')->value('id');
                
                if ($locationId) {
                    $stock = StockBalance::firstOrCreate(
                        ['item_id' => $poItem->item_master_id, 'location_id' => $locationId],
                        ['quantity_on_hand' => 0, 'quantity_reserved' => 0]
                    );
                    
                    $stock->increment('quantity_on_hand', $grItem->quantity_received);
                }
            }
        }
    }
}
