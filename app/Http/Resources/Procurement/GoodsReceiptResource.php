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

            'received_by_id' => $gr->received_by_id,
            'received_by' => $this->whenLoaded('receivedBy', fn () => [
                'id' => $gr->receivedBy->id,
                'name' => $gr->receivedBy->name,
            ]),

            'confirmed_by_id' => $gr->confirmed_by_id,
            'confirmed_by' => $this->whenLoaded('confirmedBy', fn () => $gr->confirmedBy ? [
                'id' => $gr->confirmedBy->id, 'name' => $gr->confirmedBy->name,
            ] : null),

            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => [
                'id' => $gr->purchaseOrder->id,
                'ulid' => $gr->purchaseOrder->ulid,
                'po_reference' => $gr->purchaseOrder->po_reference,
            ]),

            'items' => GoodsReceiptItemResource::collection(
                $this->whenLoaded('items')
            ),

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
