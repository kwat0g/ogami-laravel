<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\AP\Models\Vendor;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Models\User;
use App\Notifications\Procurement\PurchaseOrderSentNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class PurchaseOrderService implements ServiceContract
{
    // ── Store ────────────────────────────────────────────────────────────────

    /**
     * Create a PO from an approved PR.
     *
     * @param  array<string, mixed>       $data
     * @param  list<array<string, mixed>> $items
     */
    public function store(PurchaseRequest $pr, array $data, array $items, User $actor): PurchaseOrder
    {
        if ($pr->status !== 'approved') {
            throw new DomainException(
                message: 'A Purchase Order can only be created from an approved Purchase Request.',
                errorCode: 'PO_PR_NOT_APPROVED',
                httpStatus: 422,
            );
        }

        $vendor = Vendor::findOrFail($data['vendor_id']);

        if (! $vendor->is_active) {
            throw new DomainException(
                message: "Vendor '{$vendor->name}' is inactive and cannot receive Purchase Orders.",
                errorCode: 'PO_VENDOR_INACTIVE',
                httpStatus: 422,
            );
        }

        $vendor->assertAccredited();

        if (empty($items)) {
            throw new DomainException(
                message: 'A Purchase Order must have at least one line item.',
                errorCode: 'PO_NO_ITEMS',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($pr, $data, $items, $actor): PurchaseOrder {
            $reference = $this->generateReference();

            $po = PurchaseOrder::create([
                'po_reference'        => $reference,
                'purchase_request_id' => $pr->id,
                'vendor_id'           => $data['vendor_id'],
                'po_date'             => now()->toDateString(),
                'delivery_date'       => $data['delivery_date'],
                'payment_terms'       => $data['payment_terms'],
                'delivery_address'    => $data['delivery_address'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'status'              => 'draft',
                'total_po_amount'     => 0,
                'created_by_id'       => $actor->id,
            ]);

            foreach ($items as $index => $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'pr_item_id'        => $item['pr_item_id']    ?? null,
                    'item_master_id'    => $item['item_master_id'],
                    'item_description'  => $item['item_description'],
                    'unit_of_measure'   => $item['unit_of_measure'],
                    'quantity_ordered'  => $item['quantity_ordered'],
                    'agreed_unit_cost'  => $item['agreed_unit_cost'],
                    'quantity_received' => 0,
                    'line_order'        => $index + 1,
                ]);
            }

            // Mark the PR as converted so it cannot spawn a second PO
            $pr->update([
                'status'            => 'converted_to_po',
                'converted_to_po_id' => $po->id,
                'converted_at'      => now(),
            ]);

            return $po->refresh();
        });
    }

    // ── Send ─────────────────────────────────────────────────────────────────

    public function send(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new DomainException(
                message: "Cannot send — PO is in status '{$po->status}'.",
                errorCode: 'PO_NOT_DRAFT',
                httpStatus: 422,
            );
        }

        $po->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        // PROC-WH-001: Notify warehouse staff (procurement.goods-receipt.create) to
        // prepare for incoming goods against this PO.
        $po->loadMissing('vendor');
        $notification = new PurchaseOrderSentNotification($po);
        User::permission('procurement.goods-receipt.create')
            ->each(fn (User $u) => $u->notify($notification));

        return $po->refresh();
    }

    // ── Cancel ───────────────────────────────────────────────────────────────

    public function cancel(PurchaseOrder $po, string $reason): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new DomainException(
                message: 'Only draft Purchase Orders can be cancelled.',
                errorCode: 'PO_CANNOT_CANCEL',
                httpStatus: 422,
            );
        }

        $po->update([
            'status'               => 'cancelled',
            'cancellation_reason'  => $reason,
        ]);

        return $po->refresh();
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function generateReference(): string
    {
        $seq = DB::selectOne('SELECT NEXTVAL(\'purchase_order_seq\') AS val');
        $num = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);

        return 'PO-' . now()->format('Y-m') . '-' . $num;
    }

    // ── Finalize vendor assignment (Phase 4) ─────────────────────────────────

    /**
     * Assign a vendor to an auto-created PO draft and map each PO line
     * to a VendorItem from the vendor's catalog.
     *
     * @param  array<string, mixed>           $poData    vendor_id, delivery_date, payment_terms, etc.
     * @param  list<array<string, mixed>>     $itemUpdates  [{ po_item_id, item_master_id?, vendor_item_id?, agreed_unit_cost }]
     */
    public function finalizeVendorAssignment(PurchaseOrder $po, array $poData, array $itemUpdates, User $actor): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new DomainException(
                message: "Only draft POs can have their vendor assigned (current: '{$po->status}').",
                errorCode: 'PO_NOT_DRAFT',
                httpStatus: 422,
            );
        }

        if ($po->vendor_id !== null) {
            throw new DomainException(
                message: 'This PO already has a vendor assigned.',
                errorCode: 'PO_VENDOR_ALREADY_SET',
                httpStatus: 422,
            );
        }

        $vendor = Vendor::findOrFail($poData['vendor_id']);
        $vendor->assertAccredited();

        return DB::transaction(function () use ($po, $poData, $itemUpdates, $vendor, $actor): PurchaseOrder {
            $po->update([
                'vendor_id'        => $vendor->id,
                'delivery_date'    => $poData['delivery_date']    ?? null,
                'payment_terms'    => $poData['payment_terms']    ?? null,
                'delivery_address' => $poData['delivery_address'] ?? null,
                'notes'            => $poData['notes']            ?? $po->notes,
            ]);

            foreach ($itemUpdates as $upd) {
                $poItem = PurchaseOrderItem::where('purchase_order_id', $po->id)
                    ->findOrFail($upd['po_item_id']);

                $poItem->update([
                    'item_master_id'   => $upd['item_master_id']  ?? $poItem->item_master_id,
                    'agreed_unit_cost' => $upd['agreed_unit_cost'] ?? $poItem->agreed_unit_cost,
                ]);
            }

            return $po->refresh();
        });
    }

    // ── Auto-create from approved PR ─────────────────────────────────────────

    /**
     * Called by PurchaseRequestService::vpApprove() after PR is approved.
     *
     * Creates a PO draft with vendor_id = null and item_master_id = null.
     * The Purchasing Officer will later assign the vendor and map items from the vendor catalog.
     */
    public function autoCreateFromPr(PurchaseRequest $pr): PurchaseOrder
    {
        return DB::transaction(function () use ($pr): PurchaseOrder {
            $reference = $this->generateReference();

            $po = PurchaseOrder::create([
                'po_reference'        => $reference,
                'purchase_request_id' => $pr->id,
                'vendor_id'           => null,  // to be assigned by Purchasing Officer
                'po_date'             => now()->toDateString(),
                'delivery_date'       => null,
                'payment_terms'       => null,
                'delivery_address'    => null,
                'notes'               => "Auto-created from approved PR {$pr->pr_reference}.",
                'status'              => 'draft',
                'total_po_amount'     => 0,
                'created_by_id'       => $pr->vp_approved_by_id,
            ]);

            foreach ($pr->items as $index => $prItem) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'pr_item_id'        => $prItem->id,
                    'item_master_id'    => null,   // Purchasing Officer maps via vendor catalog
                    'item_description'  => $prItem->item_description,
                    'unit_of_measure'   => $prItem->unit_of_measure,
                    'quantity_ordered'  => $prItem->quantity,
                    'agreed_unit_cost'  => $prItem->estimated_unit_cost,
                    'quantity_received' => 0,
                    'line_order'        => $index + 1,
                ]);
            }

            // Mark the PR as converted so it cannot spawn a second PO
            $pr->update([
                'status'             => 'converted_to_po',
                'converted_to_po_id' => $po->id,
                'converted_at'       => now(),
            ]);

            return $po->refresh();
        });
    }
}
