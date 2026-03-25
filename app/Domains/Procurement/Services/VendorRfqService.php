<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\AP\Models\Vendor;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Domains\Procurement\Models\VendorRfq;
use App\Domains\Procurement\Models\VendorRfqVendor;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class VendorRfqService implements ServiceContract
{
    // ── Create ───────────────────────────────────────────────────────────────

    /**
     * Create a new RFQ draft, optionally linked to a Purchase Request.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): VendorRfq
    {
        return DB::transaction(function () use ($data, $actor): VendorRfq {
            $seq = DB::selectOne('SELECT NEXTVAL(\'vendor_rfq_seq\') AS val');
            $num = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);
            $reference = 'RFQ-'.now()->format('Y-m').'-'.$num;

            return VendorRfq::create([
                'rfq_reference' => $reference,
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'status' => 'draft',
                'deadline_date' => $data['deadline_date'] ?? null,
                'scope_description' => $data['scope_description'],
                'notes' => $data['notes'] ?? null,
                'created_by_id' => $actor->id,
            ]);
        });
    }

    // ── Invite vendors ───────────────────────────────────────────────────────

    /**
     * Mark RFQ as sent and attach vendor invitation records.
     *
     * @param  list<int>  $vendorIds
     */
    public function send(VendorRfq $rfq, array $vendorIds, User $actor): VendorRfq
    {
        if ($rfq->status !== 'draft') {
            throw new DomainException(
                message: "RFQ cannot be sent from status '{$rfq->status}'.",
                errorCode: 'RFQ_NOT_DRAFT',
                httpStatus: 422,
            );
        }

        if (empty($vendorIds)) {
            throw new DomainException(
                message: 'At least one vendor must be invited.',
                errorCode: 'RFQ_NO_VENDORS',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($rfq, $vendorIds): VendorRfq {
            foreach ($vendorIds as $vendorId) {
                VendorRfqVendor::firstOrCreate(
                    ['rfq_id' => $rfq->id, 'vendor_id' => $vendorId],
                    ['status' => 'invited'],
                );
            }

            $rfq->update(['status' => 'sent', 'sent_at' => now()]);

            return $rfq->refresh();
        });
    }

    // ── Record quote ─────────────────────────────────────────────────────────

    /**
     * Record a vendor's quotation response for an RFQ.
     *
     * @param  array<string, mixed>  $data
     */
    public function receiveQuote(VendorRfq $rfq, Vendor $vendor, array $data): VendorRfq
    {
        if (! in_array($rfq->status, ['sent', 'quote_received'], true)) {
            throw new DomainException(
                message: "Cannot receive a quote — RFQ status is '{$rfq->status}'.",
                errorCode: 'RFQ_NOT_SENT',
                httpStatus: 422,
            );
        }

        $invitation = VendorRfqVendor::where('rfq_id', $rfq->id)
            ->where('vendor_id', $vendor->id)
            ->first();

        if ($invitation === null) {
            throw new DomainException(
                message: 'Vendor was not invited to this RFQ.',
                errorCode: 'RFQ_VENDOR_NOT_INVITED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($rfq, $invitation, $data): VendorRfq {
            $invitation->update([
                'status' => 'quoted',
                'quoted_amount_centavos' => $data['quoted_amount_centavos'],
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'vendor_remarks' => $data['vendor_remarks'] ?? null,
                'responded_at' => now(),
            ]);

            $rfq->update(['status' => 'quote_received']);

            return $rfq->refresh();
        });
    }

    // ── Record decline ───────────────────────────────────────────────────────

    public function recordDecline(VendorRfq $rfq, Vendor $vendor, ?string $remarks = null): void
    {
        $invitation = VendorRfqVendor::where('rfq_id', $rfq->id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $invitation->update([
            'status' => 'declined',
            'vendor_remarks' => $remarks,
            'responded_at' => now(),
        ]);
    }

    // ── Close RFQ ────────────────────────────────────────────────────────────

    public function close(VendorRfq $rfq): VendorRfq
    {
        if (in_array($rfq->status, ['closed', 'cancelled'], true)) {
            throw new DomainException(
                message: "RFQ is already '{$rfq->status}'.",
                errorCode: 'RFQ_ALREADY_CLOSED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($rfq): VendorRfq {
            $rfq->update(['status' => 'closed', 'closed_at' => now()]);

            return $rfq->refresh();
        });
    }

    // ── Award ────────────────────────────────────────────────────────────────

    /**
     * Award the RFQ to a vendor and auto-create a draft Purchase Order.
     * Requires: rfq.status === 'quote_received', vendor has a 'quoted' invitation.
     */
    public function award(VendorRfq $rfq, Vendor $vendor, User $actor): PurchaseOrder
    {
        if ($rfq->status !== 'quote_received') {
            throw new DomainException(
                message: "RFQ must be in 'quote_received' status to award. Current: '{$rfq->status}'.",
                errorCode: 'RFQ_CANNOT_AWARD',
                httpStatus: 422,
            );
        }

        $invitation = VendorRfqVendor::where('vendor_rfq_id', $rfq->id)
            ->where('vendor_id', $vendor->id)
            ->where('status', 'quoted')
            ->first();

        if (! $invitation) {
            throw new DomainException(
                message: 'Selected vendor has not submitted a quote for this RFQ.',
                errorCode: 'RFQ_VENDOR_NOT_QUOTED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($rfq, $vendor, $actor, $invitation): PurchaseOrder {
            // Mark vendor as selected
            $invitation->update(['is_selected' => true]);

            // Close RFQ
            $rfq->update(['status' => 'closed', 'closed_at' => now()]);

            // Generate PO reference
            $seq = DB::selectOne("SELECT NEXTVAL('purchase_order_seq') AS val");
            $num = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);
            $reference = 'PO-'.now()->format('Y-m').'-'.$num;

            $po = PurchaseOrder::create([
                'po_reference' => $reference,
                'purchase_request_id' => $rfq->purchase_request_id,
                'vendor_id' => $vendor->id,
                'po_date' => now()->toDateString(),
                'payment_terms' => $vendor->payment_terms ?? 'Net 30',
                'delivery_address' => '',
                'status' => 'draft',
                'po_type' => 'standard',
                'created_by_id' => $actor->id,
                'notes' => "Created from RFQ {$rfq->rfq_reference}.",
            ]);

            // Create PO items from PR items (if linked) or single line from scope
            if ($rfq->purchase_request_id && $rfq->purchaseRequest) {
                $prItems = $rfq->purchaseRequest->items;
                $totalEstimated = $prItems->sum(fn ($i) => (float) $i->estimated_total);
                $quotedCentavos = (int) $invitation->quoted_amount_centavos;

                foreach ($prItems as $lineOrder => $prItem) {
                    // Distribute quoted amount proportionally by estimated_total
                    $proportion = $totalEstimated > 0
                        ? ((float) $prItem->estimated_total / $totalEstimated)
                        : (1 / max(1, $prItems->count()));
                    $agreedCost = (int) round($quotedCentavos * $proportion);

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'pr_item_id' => $prItem->id,
                        'item_master_id' => $prItem->item_master_id ?? null,
                        'item_description' => $prItem->item_description,
                        'unit_of_measure' => $prItem->unit_of_measure,
                        'quantity_ordered' => $prItem->quantity,
                        'agreed_unit_cost' => $agreedCost,
                        'line_order' => $lineOrder + 1,
                    ]);
                }
            } else {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'item_description' => $rfq->scope_description,
                    'unit_of_measure' => 'lot',
                    'quantity_ordered' => 1,
                    'agreed_unit_cost' => (int) $invitation->quoted_amount_centavos,
                    'line_order' => 1,
                ]);
            }

            return $po->load('items');
        });
    }

    // ── Cancel ───────────────────────────────────────────────────────────────

    public function cancel(VendorRfq $rfq): VendorRfq
    {
        if (in_array($rfq->status, ['closed', 'cancelled'], true)) {
            throw new DomainException(
                message: "RFQ is already '{$rfq->status}'.",
                errorCode: 'RFQ_ALREADY_CLOSED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($rfq): VendorRfq {
            $rfq->update(['status' => 'cancelled']);

            return $rfq->refresh();
        });
    }
}
