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

    public function __construct(
        private readonly int $leaveRequestId,
        private readonly int $employeeId,
        private readonly string $employeeName,
        private readonly string $leaveTypeName,
        private readonly string $dateFrom,
        private readonly string $dateTo,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(LeaveRequest $request): self
    {
        return new self(
            leaveRequestId: $request->id,
            employeeId: $request->employee_id,
            employeeName: $request->employee?->full_name ?? 'An employee',
            leaveTypeName: $request->leaveType?->name ?? 'leave',
            dateFrom: $request->date_from?->toFormattedDateString() ?? '',
            dateTo: $request->date_to?->toFormattedDateString() ?? '',
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
            'type' => 'leave.filed',
            'title' => 'New Leave Request',
            'message' => sprintf(
                '%s filed a %s leave request (%s – %s).',
                $this->employeeName,
                $this->leaveTypeName,
                $this->dateFrom,
                $this->dateTo,
            ),
            'action_url' => '/team/leave',
            'leave_request_id' => $this->leaveRequestId,
            'employee_id' => $this->employeeId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
