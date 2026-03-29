<?php

declare(strict_types=1);

namespace App\Http\Resources\Procurement;

use App\Domains\Procurement\Models\GoodsReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GoodsReceiptItem */
final class GoodsReceiptItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_item_id' => $this->po_item_id,
            'item_master_id' => $this->item_master_id,
            'quantity_received' => (float) $this->quantity_received,
            'unit_of_measure' => $this->unit_of_measure,
            'condition' => $this->condition,
            'remarks' => $this->remarks,

            // QC fields
            'qc_status' => $this->qc_status,
            'quantity_accepted' => $this->quantity_accepted !== null ? (float) $this->quantity_accepted : null,
            'quantity_rejected' => $this->quantity_rejected !== null ? (float) $this->quantity_rejected : null,
            'defect_type' => $this->defect_type,
            'defect_description' => $this->defect_description,
            'reject_disposition' => $this->reject_disposition,
            'disposition_completed_at' => $this->disposition_completed_at,
            'ncr_id' => $this->ncr_id,
            'ncr' => $this->whenLoaded('ncr', fn () => $this->ncr ? [
                'id' => $this->ncr->id,
                'ulid' => $this->ncr->ulid,
                'ncr_reference' => $this->ncr->ncr_reference ?? null,
                'title' => $this->ncr->title,
                'severity' => $this->ncr->severity,
                'status' => $this->ncr->status,
            ] : null),

            'po_item' => $this->whenLoaded('poItem', fn () => $this->poItem ? [
                'id' => $this->poItem->id,
                'item_description' => $this->poItem->item_description,
                'quantity_ordered' => (float) $this->poItem->quantity_ordered,
                'quantity_received' => (float) $this->poItem->quantity_received,
                'agreed_unit_cost' => $this->poItem->agreed_unit_cost,
            ] : null),
        ];
    }
}
