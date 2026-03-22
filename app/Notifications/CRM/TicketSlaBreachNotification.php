<?php

declare(strict_types=1);

namespace App\Notifications\CRM;

use App\Domains\CRM\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class TicketSlaBreachNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $ticketId,
        private readonly string $ticketUlid,
        private readonly string $ticketNumber,
        private readonly string $subject,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(Ticket $ticket): self
    {
        return new self(
            ticketId: $ticket->id,
            ticketUlid: $ticket->ulid,
            ticketNumber: (string) ($ticket->ticket_number ?? $ticket->id),
            subject: (string) ($ticket->subject ?? ''),
        );
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
            'type' => 'crm.sla_breach',
            'title' => '⚠️ Ticket SLA Breached',
            'message' => sprintf(
                'Ticket #%s "%s" has breached its SLA. Immediate action required.',
                $this->ticketNumber,
                $this->subject,
            ),
            'action_url' => "/crm/tickets/{$this->ticketUlid}",
            'ticket_id' => $this->ticketId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
