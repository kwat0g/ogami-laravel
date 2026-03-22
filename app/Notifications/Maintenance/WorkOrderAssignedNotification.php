<?php

declare(strict_types=1);

namespace App\Notifications\Maintenance;

use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class WorkOrderAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $workOrderId,
        private readonly string $workOrderUlid,
        private readonly string $title,
        private readonly string $priority,
        private readonly string $equipmentName,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(MaintenanceWorkOrder $workOrder): self
    {
        return new self(
            workOrderId: $workOrder->id,
            workOrderUlid: $workOrder->ulid,
            title: $workOrder->title,
            priority: $workOrder->priority,
            equipmentName: $workOrder->equipment?->name ?? '—',
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
            'type' => 'maintenance.work_order_assigned',
            'title' => 'Work Order Assigned to You',
            'message' => sprintf(
                'Work order "%s" (%s priority) has been assigned to you. Equipment: %s.',
                $this->title,
                $this->priority,
                $this->equipmentName,
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
