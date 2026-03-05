<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Attendance\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the employee's direct manager when an overtime request is filed.
 */
final class OvertimeRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly OvertimeRequest $request)
    {
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
        $hours = round($this->request->requested_minutes / 60, 2);

        return [
            'type' => 'overtime.requested',
            'title' => 'New Overtime Request',
            'message' => sprintf(
                '%s has requested %s hour(s) of overtime on %s. Reason: %s',
                $this->request->employee->full_name,
                $hours,
                $this->request->work_date->toFormattedDateString(),
                $this->request->reason,
            ),
            'action_url' => '/hr/overtime',
            'overtime_request_id' => $this->request->id,
            'employee_id' => $this->request->employee_id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
