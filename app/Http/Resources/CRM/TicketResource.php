<?php

declare(strict_types=1);

namespace App\Http\Resources\CRM;

use App\Domains\CRM\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
final class TicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->resource;

        return [
            'id' => $ticket->id,
            'ulid' => $ticket->ulid,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'type' => $ticket->type,
            'priority' => $ticket->priority,
            'status' => $ticket->status,

            'customer_id' => $ticket->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $ticket->customer ? [
                'id' => $ticket->customer->id,
                'ulid' => $ticket->customer->ulid,
                'name' => $ticket->customer->name,
            ] : null),

            'assigned_to_id' => $ticket->assigned_to_id,
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $ticket->assignedTo ? [
                'id' => $ticket->assignedTo->id,
                'name' => $ticket->assignedTo->name,
            ] : null),

            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
            'first_response_at' => $ticket->first_response_at?->toIso8601String(),
            'sla_breached_at' => $ticket->sla_breached_at?->toIso8601String(),

            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),

            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }
}
