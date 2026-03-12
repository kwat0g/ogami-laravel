<?php

declare(strict_types=1);

namespace App\Notifications\CRM;

use App\Domains\CRM\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Ticket $ticket,
    ) {
        $this->queue = 'notifications';
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'crm.ticket_assigned',
            'title' => 'Ticket Assigned to You',
            'message' => sprintf(
                'Ticket #%s "%s" (%s priority) has been assigned to you.',
                $this->ticket->ticket_number ?? $this->ticket->id,
                $this->ticket->subject ?? '',
                $this->ticket->priority ?? 'normal',
            ),
            'action_url' => "/crm/tickets/{$this->ticket->ulid}",
            'ticket_id' => $this->ticket->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
