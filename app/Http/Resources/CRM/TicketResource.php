<?php

declare(strict_types=1);

namespace App\Http\Resources\CRM;

use App\Domains\CRM\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'customer_id' => $ticket->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $ticket->customer ? [
                'id' => $ticket->customer->id,
                'ulid' => $ticket->customer->ulid ?? null,
                'name' => $ticket->customer->name,
            ] : null),
            'client_user_id' => $ticket->client_user_id,
            'client_user' => $this->whenLoaded('clientUser', fn () => $ticket->clientUser ? [
                'id' => $ticket->clientUser->id,
                'name' => $ticket->clientUser->name,
            ] : null),
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'type' => $ticket->type,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'assigned_to_id' => $ticket->assigned_to_id,
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $ticket->assignedTo ? [
                'id' => $ticket->assignedTo->id,
                'name' => $ticket->assignedTo->name,
            ] : null),
            'resolved_at' => $ticket->resolved_at,
            'sla_due_at' => $ticket->sla_due_at,
            'first_response_at' => $ticket->first_response_at,
            'sla_breached_at' => $ticket->sla_breached_at,
            'is_sla_breached' => $ticket->isSlaBreached(),

            'messages' => $this->whenLoaded('messages'),
            'messages_count' => $this->whenCounted('messages'),

            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
        ];
    }
}
