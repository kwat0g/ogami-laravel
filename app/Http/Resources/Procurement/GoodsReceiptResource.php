<?php

declare(strict_types=1);

namespace App\Http\Resources\Procurement;

use App\Domains\Procurement\Models\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class GoodsReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var GoodsReceipt $gr */
        $gr = $this->resource;

        return [
            'id' => $gr->id,
            'ulid' => $gr->ulid,
            'gr_reference' => $gr->gr_reference,
            'purchase_order_id' => $gr->purchase_order_id,
            'received_date' => $gr->received_date,
            'delivery_note_number' => $gr->delivery_note_number,
            'condition_notes' => $gr->condition_notes,
            'status' => $gr->status,
            'three_way_match_passed' => $gr->three_way_match_passed,
            'ap_invoice_created' => $gr->ap_invoice_created,
            'ap_invoice_id' => $gr->ap_invoice_id,
            'confirmed_at' => $gr->confirmed_at?->toIso8601String(),

            // QC workflow fields
            'submitted_for_qc_at' => $gr->submitted_for_qc_at?->toIso8601String(),
            'qc_result' => $gr->qc_result,
            'qc_completed_at' => $gr->qc_completed_at?->toIso8601String(),
            'qc_notes' => $gr->qc_notes,

            // Rejection fields
            'rejection_reason' => $gr->rejection_reason,
            'rejected_at' => $gr->rejected_at?->toIso8601String(),

            // Return-to-supplier fields
            'returned_at' => $gr->returned_at?->toIso8601String(),
            'return_reason' => $gr->return_reason,

            'received_by_id' => $gr->received_by_id,
            'received_by' => $this->whenLoaded('receivedBy', fn () => $gr->receivedBy ? [
                'id' => $gr->receivedBy->id,
                'name' => $gr->receivedBy->name,
            ] : null),

            'confirmed_by_id' => $gr->confirmed_by_id,
            'confirmed_by' => $this->whenLoaded('confirmedBy', fn () => $gr->confirmedBy ? [
                'id' => $gr->confirmedBy->id, 'name' => $gr->confirmedBy->name,
            ] : null),

            'submitted_for_qc_by' => $this->whenLoaded('submittedForQcBy', fn () => $gr->submittedForQcBy ? [
                'id' => $gr->submittedForQcBy->id, 'name' => $gr->submittedForQcBy->name,
            ] : null),

            'qc_completed_by' => $this->whenLoaded('qcCompletedBy', fn () => $gr->qcCompletedBy ? [
                'id' => $gr->qcCompletedBy->id, 'name' => $gr->qcCompletedBy->name,
            ] : null),

            'rejected_by' => $this->whenLoaded('rejectedBy', fn () => $gr->rejectedBy ? [
                'id' => $gr->rejectedBy->id, 'name' => $gr->rejectedBy->name,
            ] : null),

            'returned_by' => $this->whenLoaded('returnedBy', fn () => $gr->returnedBy ? [
                'id' => $gr->returnedBy->id, 'name' => $gr->returnedBy->name,
            ] : null),

            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => [
                'id' => $gr->purchaseOrder->id,
                'ulid' => $gr->purchaseOrder->ulid,
                'po_reference' => $gr->purchaseOrder->po_reference,
            ]),

            'items' => GoodsReceiptItemResource::collection(
                $this->whenLoaded('items')
            ),

            'inspections' => $this->whenLoaded('inspections', fn () => $gr->inspections->map(fn ($i) => [
                'id' => $i->id,
                'ulid' => $i->ulid,
                'stage' => $i->stage,
                'status' => $i->status,
                'qty_inspected' => (float) $i->qty_inspected,
                'qty_passed' => (float) $i->qty_passed,
                'qty_failed' => (float) $i->qty_failed,
                'inspection_date' => $i->inspection_date,
                'remarks' => $i->remarks,
            ])),

            // Warning flag: true when one or more items have no item_master_id.
            // Those items will be skipped by auto-receive and must be resolved manually.
            'has_unlinked_items' => $this->whenLoaded(
                'items',
                fn () => $gr->hasUnlinkedItems(),
                false,
            ),

            'deleted_at' => $gr->deleted_at?->toIso8601String(),
            'created_at' => $gr->created_at?->toIso8601String(),
            'updated_at' => $gr->updated_at?->toIso8601String(),
        ];
    }
}
