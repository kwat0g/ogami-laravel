<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Leave\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the HR Manager when a supervisor has endorsed a staff leave request
 * and it is now awaiting the manager's final approval.
 */
final class LeaveSupervisorEndorsedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly LeaveRequest $request,
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
        return [
            'type' => 'leave.supervisor_endorsed',
            'title' => 'Leave Request Endorsed — Awaiting Your Approval',
            'message' => sprintf(
                '%s endorsed %s\'s %s leave request (%s – %s). The request is pending your approval.%s',
                $this->supervisorName,
                $this->request->employee->full_name,
                $this->request->leaveType->name,
                $this->request->date_from->toFormattedDateString(),
                $this->request->date_to->toFormattedDateString(),
                $this->remarks ? ' Supervisor remarks: '.$this->remarks : '',
            ),
            'action_url' => '/hr/leave',
            'leave_request_id' => $this->request->id,
            'employee_id' => $this->request->employee_id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
