<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\AP\Models\VendorFulfillmentNote;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * VendorFulfillmentService — vendor-portal fulfillment updates on POs.
 *
 * Vendors can:
 *   1. markInTransit   — notify that goods have been dispatched
 *   2. markDelivered   — confirm delivery and auto-create a GR draft for
 *                        receiving staff to confirm (three-way match follows)
 *
 * All mutations are wrapped in DB::transaction().
 */
final class VendorFulfillmentService implements ServiceContract
{
    /**
     * Notify that the PO items are in transit (shipping).
     *
     * Allowed statuses: sent, partially_received
     * Updates PO status to 'in_transit' to indicate goods are on the way.
     */
    public function markInTransit(PurchaseOrder $po, User $vendorUser, string $notes = ''): VendorFulfillmentNote
    {
        if ($po->status !== 'acknowledged') {
            throw new DomainException(
                message: "PO '{$po->po_reference}' must be acknowledged before marking in-transit (status: {$po->status}). Please acknowledge the PO or resolve any pending negotiation first.",
                errorCode: 'VFN_PO_NOT_ACKNOWLEDGED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($po, $vendorUser, $notes): VendorFulfillmentNote {
            // Update PO status to indicate goods are in transit
            $po->update(['status' => 'in_transit']);

            return VendorFulfillmentNote::create([
                'purchase_order_id' => $po->id,
                'vendor_user_id' => $vendorUser->id,
                'note_type' => 'in_transit',
                'notes' => $notes ?: null,
                'items' => null,
            ]);
        });
    }

    /**
     * Mark delivery as completed (full or partial) and auto-create a GR draft.
     *
     * When partial delivery occurs:
     * - Original PO is updated to reflect only delivered quantities
     * - A new "split" PO is created for the remaining quantities
     * - The split PO is linked to the original via parent_po_id
     *
     * @param  array<int, array{po_item_id: int, qty_delivered: float}>  $itemDeliveries
     * @return array{note: VendorFulfillmentNote, split_po: PurchaseOrder|null}
     */
    public function markDelivered(
        PurchaseOrder $po,
        User $vendorUser,
        array $itemDeliveries,
        string $notes = '',
        ?string $deliveryDate = null
    ): array {
        if (! $po->canReceiveGoods()) {
            throw new DomainException(
                message: "PO '{$po->po_reference}' cannot be delivered from status: {$po->status}.",
                errorCode: 'VFN_PO_NOT_DELIVERABLE',
                httpStatus: 422,
            );
        }

        if (empty($itemDeliveries)) {
            throw new DomainException(
                message: 'At least one item delivery quantity is required.',
                errorCode: 'VFN_NO_ITEMS',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($po, $vendorUser, $itemDeliveries, $notes, $deliveryDate): array {
            $po->loadMissing('items');

            // Validate each delivered qty against pending qty
            foreach ($itemDeliveries as $delivery) {
                $poItem = $po->items->firstWhere('id', $delivery['po_item_id']);
                if ($poItem === null) {
                    throw new DomainException(
                        message: "PO item #{$delivery['po_item_id']} not found on PO '{$po->po_reference}'.",
                        errorCode: 'VFN_ITEM_NOT_FOUND',
                        httpStatus: 422,
                    );
                }

                if ((float) $delivery['qty_delivered'] > (float) $poItem->quantity_pending) {
                    throw new DomainException(
                        message: "Delivered qty ({$delivery['qty_delivered']}) exceeds pending qty ({$poItem->quantity_pending}) for item '{$poItem->item_description}'.",
                        errorCode: 'VFN_QTY_EXCEEDS_PENDING',
                        httpStatus: 422,
                    );
                }
            }

            // Determine if any items are partially delivered
            $isPartial = false;
            foreach ($itemDeliveries as $delivery) {
                $poItem = $po->items->firstWhere('id', $delivery['po_item_id']);
                if ((float) $delivery['qty_delivered'] < (float) $poItem->quantity_pending) {
                    $isPartial = true;
                    break;
                }
            }

            // Build delivery map for easy lookup
            $deliveryMap = [];
            foreach ($itemDeliveries as $delivery) {
                $deliveryMap[$delivery['po_item_id']] = (float) $delivery['qty_delivered'];
            }

            // Calculate totals for original PO (what was delivered)
            $originalTotal = 0;
            $itemsToKeepOnOriginal = [];
            $itemsForSplitPo = [];

            foreach ($po->items as $poItem) {
                $deliveredQty = $deliveryMap[$poItem->id] ?? 0;
                $orderedQty = (float) $poItem->quantity_ordered;
                $lineTotal = (float) $poItem->agreed_unit_cost;

                if ($deliveredQty > 0) {
                    // This item has a delivery
                    $originalTotal += $lineTotal * $deliveredQty;
                    
                    // IMPORTANT: We do NOT update quantity_received here.
                    // That only happens when warehouse confirms the GR via three-way match.
                    // We only reduce the ordered quantity to what was delivered.
                    $itemsToKeepOnOriginal[] = [
                        'item' => $poItem,
                        'new_ordered' => $deliveredQty,
                    ];

                    // Check if there's remaining qty for split PO
                    $remainingQty = $orderedQty - $deliveredQty;
                    if ($remainingQty > 0.001) { // Use epsilon for float comparison
                        $itemsForSplitPo[] = [
                            'item' => $poItem,
                            'quantity' => $remainingQty,
                        ];
                    }
                } else {
                    // Item not delivered at all - it goes entirely to split PO
                    // Keep original ordered qty for split
                    $itemsForSplitPo[] = [
                        'item' => $poItem,
                        'quantity' => $orderedQty,
                    ];
                }
            }

            // Handle partial delivery: create split PO for remaining items
            $splitPo = null;
            if ($isPartial && !empty($itemsForSplitPo)) {
                $splitPo = $this->createSplitPo($po, $itemsForSplitPo, $vendorUser);
            }

            // Update original PO items to reflect delivered quantities only
            // quantity_received stays the same - will be updated by three-way match
            foreach ($itemsToKeepOnOriginal as $itemData) {
                $poItem = $itemData['item'];
                $poItem->update([
                    'quantity_ordered' => $itemData['new_ordered'],
                ]);
            }

            // Delete items from original PO that weren't delivered at all (moved to split PO)
            $itemIdsToKeep = array_map(fn($i) => $i['item']->id, $itemsToKeepOnOriginal);
            foreach ($po->items as $poItem) {
                if (!in_array($poItem->id, $itemIdsToKeep, true)) {
                    $poItem->delete();
                }
            }

            // Update original PO total and status.
            // For partial split: mark as partially_received so WH can still confirm GR.
            // For full delivery: keep in_transit — ThreeWayMatchService will set fully_received
            // once the Warehouse Head confirms the GR and quantities are verified.
            $originalStatus = $isPartial ? 'partially_received' : 'in_transit';
            $po->update([
                'total_po_amount' => $originalTotal,
                'status' => $originalStatus,
            ]);

            // Create fulfillment note
            $noteType = $isPartial ? 'partial' : 'delivered';
            $note = VendorFulfillmentNote::create([
                'purchase_order_id' => $po->id,
                'vendor_user_id' => $vendorUser->id,
                'note_type' => $noteType,
                'notes' => $notes ?: null,
                'delivery_date' => $deliveryDate,
                'items' => $itemDeliveries,
            ]);

            // Auto-create a Goods Receipt draft for the receiving staff to confirm
            $reference = 'GR-VND-'.strtoupper(substr($po->po_reference, -6)).'-'.now()->format('mdHi');

            $gr = GoodsReceipt::create([
                'gr_reference' => $reference,
                'purchase_order_id' => $po->id,
                'received_by_id' => $vendorUser->id,
                'received_date' => $deliveryDate ?? now()->toDateString(),
                'delivery_note_number' => null,
                'condition_notes' => "Auto-created from vendor delivery confirmation. {$notes}",
                'status' => 'draft',
                'three_way_match_passed' => false,
                'ap_invoice_created' => false,
            ]);

            foreach ($itemDeliveries as $delivery) {
                $poItem = $po->items->firstWhere('id', $delivery['po_item_id']);
                GoodsReceiptItem::create([
                    'goods_receipt_id' => $gr->id,
                    'po_item_id' => $poItem->id,
                    'item_master_id' => $poItem->item_master_id,
                    'quantity_received' => $delivery['qty_delivered'],
                    'unit_of_measure' => $poItem->unit_of_measure,
                    'condition' => 'good',
                ]);
            }

            return [
                'note' => $note,
                'split_po' => $splitPo,
            ];
        });
    }

    /**
     * Create a split PO for remaining quantities after partial delivery.
     *
     * @param  array<int, array{item: PurchaseOrderItem, quantity: float}>  $items
     */
    private function createSplitPo(PurchaseOrder $originalPo, array $items, User $vendorUser): PurchaseOrder
    {
        $splitReference = $originalPo->po_reference.'-SPLIT';

        // Check if split PO already exists (e.g., multiple partial deliveries)
        $existingSplit = PurchaseOrder::where('po_reference', 'like', $splitReference.'%')
            ->orderBy('id', 'desc')
            ->first();

        if ($existingSplit) {
            // Append number for subsequent splits
            $suffix = 2;
            $baseRef = $splitReference;
            if (preg_match('/-SPLIT-(\d+)$/', $existingSplit->po_reference, $matches)) {
                $suffix = (int) $matches[1] + 1;
                $baseRef = preg_replace('/-\d+$/', '', $existingSplit->po_reference);
            }
            $splitReference = $baseRef.'-'.$suffix;
        }

        // Calculate split PO total
        $splitTotal = 0;
        foreach ($items as $itemData) {
            $splitTotal += (float) $itemData['item']->agreed_unit_cost * $itemData['quantity'];
        }

        $splitPo = PurchaseOrder::create([
            'po_reference' => $splitReference,
            'purchase_request_id' => $originalPo->purchase_request_id,
            'parent_po_id' => $originalPo->id,
            'vendor_id' => $originalPo->vendor_id,
            'po_date' => now()->toDateString(),
            'delivery_date' => $originalPo->delivery_date,
            'payment_terms' => $originalPo->payment_terms,
            'delivery_address' => $originalPo->delivery_address,
            'status' => 'sent', // Ready for vendor to fulfill
            'po_type' => 'split',
            'total_po_amount' => $splitTotal,
            'created_by_id' => $vendorUser->id,
            'notes' => "Auto-created from partial delivery of {$originalPo->po_reference}. Remaining quantities.",
        ]);

        // Create items for split PO
        $lineOrder = 1;
        foreach ($items as $itemData) {
            $originalItem = $itemData['item'];
            PurchaseOrderItem::create([
                'purchase_order_id' => $splitPo->id,
                'item_master_id' => $originalItem->item_master_id,
                'item_description' => $originalItem->item_description,
                'quantity_ordered' => $itemData['quantity'],
                'quantity_received' => 0,
                'quantity_pending' => $itemData['quantity'],
                'agreed_unit_cost' => $originalItem->agreed_unit_cost,
                'unit_of_measure' => $originalItem->unit_of_measure,
                'line_order' => $lineOrder++,
            ]);
        }

        return $splitPo;
    }
}
