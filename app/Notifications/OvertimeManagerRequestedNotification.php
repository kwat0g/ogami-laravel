<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Attendance\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to Executives when a Department Manager files an overtime request.
 * Manager OT requests bypass the supervisor step and go directly to the
 * executive queue for approval.
 */
final class OvertimeManagerRequestedNotification extends Notification implements ShouldQueue
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
            'type' => 'overtime.manager_requested',
            'title' => 'Manager Overtime Request — Executive Approval Required',
            'message' => sprintf(
                '%s (Manager) has filed an overtime request for %s (%.2fh) on %s. Executive approval is required.',
                $this->request->employee->full_name,
                $this->request->work_date->toFormattedDateString(),
                $hours,
                $this->request->reason,
            ),
            'action_url' => '/executive/overtime',
            'overtime_request_id' => $this->request->id,
            'employee_id' => $this->request->employee_id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
