<?php

declare(strict_types=1);

namespace App\Http\Resources\CRM;

use App\Domains\CRM\Models\ClientOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ClientOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ClientOrder $order */
        $order = $this->resource;

        return [
            'id' => $order->id,
            'ulid' => $order->ulid,
            'order_reference' => $order->order_reference,
            'customer_id' => $order->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $order->customer ? [
                'id' => $order->customer->id,
                'ulid' => $order->customer->ulid ?? null,
                'name' => $order->customer->name,
            ] : null),
            'status' => $order->status,
            'requested_delivery_date' => $order->requested_delivery_date,
            'agreed_delivery_date' => $order->agreed_delivery_date,
            'total_amount_centavos' => $order->total_amount_centavos,
            'total_amount' => $order->total_amount_centavos / 100,
            'client_notes' => $order->client_notes,
            'internal_notes' => $order->internal_notes,
            'rejection_reason' => $order->rejection_reason,
            'negotiation_reason' => $order->negotiation_reason,
            'negotiation_notes' => $order->negotiation_notes,
            'negotiation_turn' => $order->negotiation_turn,
            'negotiation_round' => $order->negotiation_round,
            'last_proposal' => $order->last_proposal,
            'sla_deadline' => $order->sla_deadline,

            // Actors
            'submitted_by' => $this->whenLoaded('submittedBy', fn () => $order->submittedBy ? [
                'id' => $order->submittedBy->id,
                'name' => $order->submittedBy->name,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $order->approvedBy ? [
                'id' => $order->approvedBy->id,
                'name' => $order->approvedBy->name,
            ] : null),
            'rejected_by' => $this->whenLoaded('rejectedBy', fn () => $order->rejectedBy ? [
                'id' => $order->rejectedBy->id,
                'name' => $order->rejectedBy->name,
            ] : null),
            'vp_approved_by' => $this->whenLoaded('vpApprovedBy', fn () => $order->vpApprovedBy ? [
                'id' => $order->vpApprovedBy->id,
                'name' => $order->vpApprovedBy->name,
            ] : null),

            'approved_at' => $order->approved_at,
            'rejected_at' => $order->rejected_at,
            'submitted_at' => $order->submitted_at,
            'vp_approved_at' => $order->vp_approved_at,
            'cancelled_at' => $order->cancelled_at,

            // Relations
            'items' => $this->whenLoaded('items'),
            'items_count' => $this->whenCounted('items'),
            'delivery_schedule' => $this->whenLoaded('deliverySchedule'),

            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'deleted_at' => $order->deleted_at,
        ];
    }
}
