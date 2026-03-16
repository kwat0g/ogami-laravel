<?php

declare(strict_types=1);

namespace App\Http\Resources\Procurement;

use App\Domains\Procurement\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PurchaseOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var PurchaseOrder $po */
        $po = $this->resource;

        return [
            'id' => $po->id,
            'ulid' => $po->ulid,
            'po_reference' => $po->po_reference,
            'purchase_request_id' => $po->purchase_request_id,
            'vendor_id' => $po->vendor_id,
            'po_date' => $po->po_date,
            'delivery_date' => $po->delivery_date,
            'payment_terms' => $po->payment_terms,
            'delivery_address' => $po->delivery_address,
            'status' => $po->status,
            'total_po_amount' => (float) $po->total_po_amount,
            'notes' => $po->notes,
            'sent_at' => $po->sent_at?->toIso8601String(),
            'closed_at' => $po->closed_at?->toIso8601String(),
            'cancellation_reason' => $po->cancellation_reason,

            'created_by_id' => $po->created_by_id,
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $po->createdBy->id,
                'name' => $po->createdBy->name,
            ]),

            'vendor' => $this->whenLoaded('vendor', fn () => [
                'id' => $po->vendor->id,
                'name' => $po->vendor->name,
            ]),

            'purchase_request' => $this->whenLoaded('purchaseRequest', fn () => [
                'id' => $po->purchaseRequest->id,
                'ulid' => $po->purchaseRequest->ulid,
                'pr_reference' => $po->purchaseRequest->pr_reference,
            ]),

            'items' => PurchaseOrderItemResource::collection(
                $this->whenLoaded('items')
            ),

            'deleted_at' => $po->deleted_at?->toIso8601String(),
            'created_at' => $po->created_at?->toIso8601String(),
            'updated_at' => $po->updated_at?->toIso8601String(),
        ];
    }
}
