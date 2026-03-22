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
        private readonly int $leaveRequestId,
        private readonly int $employeeId,
        private readonly string $employeeName,
        private readonly string $leaveTypeName,
        private readonly string $dateFrom,
        private readonly string $dateTo,
        /** Name of the supervisor who endorsed. */
        private readonly string $supervisorName,
        private readonly ?string $remarks = null,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(
        LeaveRequest $request,
        string $supervisorName,
        ?string $remarks = null
    ): self {
        return new self(
            leaveRequestId: $request->id,
            employeeId: $request->employee_id,
            employeeName: $request->employee->full_name,
            leaveTypeName: $request->leaveType->name,
            dateFrom: $request->date_from->toFormattedDateString(),
            dateTo: $request->date_to->toFormattedDateString(),
            supervisorName: $supervisorName,
            remarks: $remarks,
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
            'type' => 'leave.supervisor_endorsed',
            'title' => 'Leave Request Endorsed — Awaiting Your Approval',
            'message' => sprintf(
                '%s endorsed %s\'s %s leave request (%s – %s). The request is pending your approval.%s',
                $this->supervisorName,
                $this->employeeName,
                $this->leaveTypeName,
                $this->dateFrom,
                $this->dateTo,
                $this->remarks ? ' Supervisor remarks: '.$this->remarks : '',
            ),
            'action_url' => '/hr/leave',
            'leave_request_id' => $this->leaveRequestId,
            'employee_id' => $this->employeeId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
