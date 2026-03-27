<?php

declare(strict_types=1);

namespace App\Http\Resources\CRM;

use App\Domains\CRM\Models\ClientOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClientOrder */
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
            'status' => $order->status,
            'requested_delivery_date' => $order->requested_delivery_date?->toDateString(),
            'agreed_delivery_date' => $order->agreed_delivery_date?->toDateString(),
            'total_amount_centavos' => $order->total_amount_centavos,
            'client_notes' => $order->client_notes,
            'internal_notes' => $order->internal_notes,
            'rejection_reason' => $order->rejection_reason,
            'negotiation_reason' => $order->negotiation_reason,
            'negotiation_notes' => $order->negotiation_notes,
            'negotiation_turn' => $order->negotiation_turn,
            'negotiation_round' => $order->negotiation_round,
            'last_proposal' => $order->last_proposal,
            'sla_deadline' => $order->sla_deadline?->toIso8601String(),

            // Customer
            'customer_id' => $order->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $order->customer ? [
                'id' => $order->customer->id,
                'ulid' => $order->customer->ulid,
                'name' => $order->customer->name,
            ] : null),

            // Actors
            'submitted_by' => $order->submitted_by,
            'submitted_at' => $order->submitted_at?->toIso8601String(),
            'approved_by' => $order->approved_by,
            'approved_at' => $order->approved_at?->toIso8601String(),
            'rejected_by' => $order->rejected_by,
            'rejected_at' => $order->rejected_at?->toIso8601String(),
            'vp_approved_by' => $order->vp_approved_by,
            'vp_approved_at' => $order->vp_approved_at?->toIso8601String(),
            'cancelled_by' => $order->cancelled_by,
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),

            // Relations
            'items' => ClientOrderItemResource::collection($this->whenLoaded('items')),

            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }
}
