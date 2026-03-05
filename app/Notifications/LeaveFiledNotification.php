<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Leave\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the manager when an employee files a leave request.
 * Stored in the `notifications` table + broadcast on the manager's private channel.
 */
final class LeaveFiledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly LeaveRequest $request)
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
        return [
            'type' => 'leave.filed',
            'title' => 'New Leave Request',
            'message' => sprintf(
                '%s filed a %s leave request (%s – %s).',
                $this->request->employee?->full_name ?? 'An employee',
                $this->request->leaveType?->name ?? 'leave',
                $this->request->date_from?->toFormattedDateString(),
                $this->request->date_to?->toFormattedDateString(),
            ),
            'action_url' => '/team/leave',
            'leave_request_id' => $this->request->id,
            'employee_id' => $this->request->employee_id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
