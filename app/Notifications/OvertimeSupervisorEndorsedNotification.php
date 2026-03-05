<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Attendance\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the Department Manager when a supervisor has endorsed a staff
 * overtime request and it is now awaiting the manager's final approval.
 */
final class OvertimeSupervisorEndorsedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly OvertimeRequest $request,
        /** Name of the supervisor who endorsed. */
        private readonly string $supervisorName,
        private readonly ?string $remarks = null,
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
        $hours = round($this->request->requested_minutes / 60, 2);

        return [
            'type' => 'overtime.supervisor_endorsed',
            'title' => 'Overtime Request Endorsed — Awaiting Your Approval',
            'message' => sprintf(
                '%s endorsed %s\'s overtime request for %s (%.2fh). The request is pending your final approval.%s',
                $this->supervisorName,
                $this->request->employee->full_name,
                $this->request->work_date->toFormattedDateString(),
                $hours,
                $this->remarks ? ' Supervisor remarks: '.$this->remarks : '',
            ),
            'action_url' => '/team/overtime',
            'overtime_request_id' => $this->request->id,
            'employee_id' => $this->request->employee_id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
