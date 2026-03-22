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
        private readonly int $workOrderId,
        private readonly string $workOrderUlid,
        private readonly string $title,
        private readonly ?string $scheduledDate,
        private readonly int $daysOverdue,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(MaintenanceWorkOrder $workOrder, int $daysOverdue): self
    {
        return new self(
            workOrderId: $workOrder->id,
            workOrderUlid: $workOrder->ulid,
            title: $workOrder->title,
            scheduledDate: $workOrder->scheduled_date?->format('M d, Y'),
            daysOverdue: $daysOverdue,
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
            'type' => 'maintenance.work_order_overdue',
            'title' => 'Work Order Overdue',
            'message' => sprintf(
                'Work order "%s" is %d days overdue. Scheduled for: %s.',
                $this->title,
                $this->daysOverdue,
                $this->scheduledDate ?? '—',
            ),
            'action_url' => "/maintenance/work-orders/{$this->workOrderUlid}",
            'work_order_id' => $this->workOrderId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
