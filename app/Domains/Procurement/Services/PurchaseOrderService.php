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
                    'pr_item_id'        => $item['pr_item_id']   ?? null,
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
}
