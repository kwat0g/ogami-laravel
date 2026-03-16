<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\AP\Models\VendorFulfillmentNote;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Domains\Procurement\Models\PurchaseOrder;
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
     * Notify that the PO items are in transit.
     *
     * Allowed statuses: sent, partially_received
     */
    public function markInTransit(PurchaseOrder $po, User $vendorUser, string $notes = ''): VendorFulfillmentNote
    {
        if (! $po->canReceiveGoods()) {
            throw new DomainException(
                message: "PO '{$po->po_reference}' is not in a shippable state (status: {$po->status}).",
                errorCode: 'VFN_PO_NOT_SHIPPABLE',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($po, $vendorUser, $notes): VendorFulfillmentNote {
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
     * @param  array<int, array{po_item_id: int, qty_delivered: float}>  $itemDeliveries
     */
    public function markDelivered(
        PurchaseOrder $po,
        User $vendorUser,
        array $itemDeliveries,
        string $notes = ''
    ): VendorFulfillmentNote {
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

        return DB::transaction(function () use ($po, $vendorUser, $itemDeliveries, $notes): VendorFulfillmentNote {
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

            $noteType = $isPartial ? 'partial' : 'delivered';

            $note = VendorFulfillmentNote::create([
                'purchase_order_id' => $po->id,
                'vendor_user_id' => $vendorUser->id,
                'note_type' => $noteType,
                'notes' => $notes ?: null,
                'items' => $itemDeliveries,
            ]);

            // Auto-create a Goods Receipt draft for the receiving staff to confirm
            $reference = 'GR-VND-'.strtoupper(substr($po->po_reference, -6)).'-'.now()->format('mdHi');

            $gr = GoodsReceipt::create([
                'gr_reference' => $reference,
                'purchase_order_id' => $po->id,
                'received_by_id' => $vendorUser->id,
                'received_date' => now()->toDateString(),
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

            return $note;
        });
    }
}
