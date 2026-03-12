<?php

declare(strict_types=1);

namespace App\Notifications\Maintenance;

use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class WorkOrderOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly MaintenanceWorkOrder $workOrder,
        private readonly int $daysOverdue,
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
            'type' => 'maintenance.work_order_overdue',
            'title' => 'Work Order Overdue',
            'message' => sprintf(
                'Work order "%s" is %d days overdue. Scheduled for: %s.',
                $this->workOrder->title,
                $this->daysOverdue,
                $this->workOrder->scheduled_date?->format('M d, Y') ?? '—',
            ),
            'action_url' => "/maintenance/work-orders/{$this->workOrder->ulid}",
            'work_order_id' => $this->workOrder->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
