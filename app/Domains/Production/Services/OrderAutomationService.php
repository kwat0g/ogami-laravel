<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\CRM\Models\ClientOrder;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\ProductionOrder;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OrderAutomationService
 *
 * Automatically creates Production Orders when a Client Order is approved.
 * For each line item in the approved order, finds the matching BOM and creates
 * a production order with the required quantity.
 *
 * Flow: Client Order approved -> Production Order(s) created -> MRQ auto-created from BOM
 *
 * The production orders are created in 'draft' status. The production manager
 * still needs to release them (which triggers material availability checks).
 */
final class OrderAutomationService implements ServiceContract
{
    public function __construct(
        private readonly ProductionOrderService $productionOrderService,
    ) {}

    /**
     * Create production orders from an approved Client Order.
     *
     * @return list<ProductionOrder> Created production orders
     */
    public function createFromClientOrder(ClientOrder $order, User $actor): array
    {
        if ($order->status !== 'approved') {
            Log::warning('[OrderAutomation] Client order not in approved status', [
                'order_id' => $order->id,
                'status' => $order->status,
            ]);
            return [];
        }

        // Guard: don't create duplicates
        $existingCount = ProductionOrder::where('client_order_id', $order->id)->count();
        if ($existingCount > 0) {
            Log::info('[OrderAutomation] Production orders already exist for this client order', [
                'order_id' => $order->id,
                'existing_count' => $existingCount,
            ]);
            return [];
        }

        $items = $order->items ?? [];
        if (count($items) === 0) {
            Log::warning('[OrderAutomation] Client order has no items', ['order_id' => $order->id]);
            return [];
        }

        return DB::transaction(function () use ($order, $items, $actor): array {
            $createdOrders = [];

            foreach ($items as $item) {
                $productItemId = $item->item_master_id ?? $item->product_item_id ?? null;
                if (! $productItemId) {
                    Log::warning('[OrderAutomation] Order item has no product reference', [
                        'item_id' => $item->id,
                    ]);
                    continue;
                }

                $qty = (float) ($item->quantity ?? $item->qty ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                // Find the active BOM for this product
                $bom = BillOfMaterials::where('product_item_id', $productItemId)
                    ->where('is_active', true)
                    ->orderByDesc('version')
                    ->first();

                if (! $bom) {
                    Log::warning('[OrderAutomation] No active BOM found for product', [
                        'product_item_id' => $productItemId,
                    ]);
                    continue;
                }

                // Calculate target dates (default: start in 3 days, end in 14 days)
                $deliveryDate = $item->delivery_date ?? $order->delivery_date ?? null;
                $targetEnd = $deliveryDate
                    ? \Carbon\Carbon::parse($deliveryDate)->subDays(2)->toDateString()
                    : now()->addDays(14)->toDateString();
                $targetStart = now()->addDays(3)->toDateString();

                // Delegate to ProductionOrderService::store() for consistent
                // po_reference generation, BOM snapshot, and cost estimation.
                $productionOrder = $this->productionOrderService->store([
                    'client_order_id' => $order->id,
                    'source_type' => 'client_order',
                    'source_id' => $order->id,
                    'product_item_id' => $productItemId,
                    'bom_id' => $bom->id,
                    'qty_required' => $qty,
                    'target_start_date' => $targetStart,
                    'target_end_date' => $targetEnd,
                    'notes' => "Auto-created from Client Order {$order->order_reference}",
                ], $actor);

                $createdOrders[] = $productionOrder;

                Log::info('[OrderAutomation] Production order created', [
                    'production_order_id' => $productionOrder->id,
                    'client_order_id' => $order->id,
                    'product_item_id' => $productItemId,
                    'qty' => $qty,
                ]);
            }

            return $createdOrders;
        });
    }
}
