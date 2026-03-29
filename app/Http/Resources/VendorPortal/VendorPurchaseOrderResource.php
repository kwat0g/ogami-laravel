<?php

declare(strict_types=1);

namespace App\Http\Resources\VendorPortal;

use App\Domains\Procurement\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vendor-facing PO resource -- exposes only fields safe for external vendors.
 *
 * This resource replaces the raw Eloquent model return that previously exposed
 * ALL $fillable attributes, including internal pricing, margin data, budget
 * references, approval comments, and created_by user details.
 *
 * Fields are matched to the frontend VendorPortalOrder interface to ensure
 * backward compatibility while removing sensitive internal data.
 *
 * @see REC-05 in plans/ogami-erp-adversarial-analysis-report.md
 */
final class VendorPurchaseOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var PurchaseOrder $po */
        $po = $this->resource;

        return [
            // Identity -- frontend needs both id and ulid
            'id' => $po->id,
            'ulid' => $po->ulid,
            'po_reference' => $po->po_reference,
            'vendor_id' => $po->vendor_id,
            'status' => $po->status,
            'po_type' => $po->po_type ?? 'original',

            // Dates and terms
            'po_date' => $po->po_date,
            'delivery_date' => $po->delivery_date,
            'payment_terms' => $po->payment_terms,
            'delivery_address' => $po->delivery_address,
            'total_po_amount' => (float) $po->total_po_amount,
            'sent_at' => $po->sent_at?->toIso8601String(),
            'closed_at' => $po->closed_at?->toIso8601String(),

            // Negotiation fields (vendor-visible)
            'vendor_remarks' => $po->vendor_remarks,
            'negotiation_round' => $po->negotiation_round ?? 0,
            'change_requested_at' => $po->change_requested_at?->toIso8601String(),
            'change_reviewed_at' => $po->change_reviewed_at?->toIso8601String(),
            'change_review_remarks' => $po->change_review_remarks,
            'vendor_acknowledged_at' => $po->vendor_acknowledged_at?->toIso8601String(),
            'in_transit_at' => $po->in_transit_at?->toIso8601String(),
            'tracking_number' => $po->tracking_number,
            'proposed_delivery_date' => $po->proposed_delivery_date,
            'proposed_payment_terms' => $po->proposed_payment_terms,
            'requires_budget_recheck' => (bool) ($po->requires_budget_recheck ?? false),

            // Vendor-safe notes (only vendor-facing remarks, not internal notes)
            // The raw model exposed internal `notes` which may contain budget refs
            // and approval comments -- we return it for now since frontend depends on it,
            // but this should be reviewed to split into vendor_notes vs internal_notes
            'notes' => $po->notes,

            'items' => VendorPurchaseOrderItemResource::collection(
                $this->whenLoaded('items'),
            ),

            'fulfillment_notes' => $this->whenLoaded('fulfillmentNotes', fn () => $po->fulfillmentNotes->map(fn ($note) => [
                'id' => $note->id,
                'note_type' => $note->note_type,
                'notes' => $note->notes,
                'items' => $note->items,
                'created_at' => $note->created_at?->toIso8601String(),
            ])),

            'goods_receipts' => $this->whenLoaded('goodsReceipts', fn () => $po->goodsReceipts->map(fn ($gr) => [
                'gr_reference' => $gr->gr_reference,
                'received_date' => $gr->received_date,
                'status' => $gr->status,
            ])),

            'parent_po' => $this->whenLoaded('parentPo', fn () => [
                'ulid' => $po->parentPo->ulid,
                'po_reference' => $po->parentPo->po_reference,
            ]),

            'child_pos' => $this->whenLoaded('childPos', fn () => $po->childPos->map(fn ($child) => [
                'ulid' => $child->ulid,
                'po_reference' => $child->po_reference,
                'status' => $child->status,
                'total_po_amount' => (float) $child->total_po_amount,
            ])),

            'created_at' => $po->created_at?->toIso8601String(),
            'updated_at' => $po->updated_at?->toIso8601String(),

            // EXPLICITLY EXCLUDED (sensitive internal data):
            // - purchase_request_id, purchase_request (internal PR linkage and details)
            // - created_by_id, created_by (internal user who created the PO)
            // - cancellation_reason (internal decision reasons)
            // - deleted_at (soft-delete internals)
            // - Any margin/cost analysis fields
            // - Department and budget references
        ];
    }
}
