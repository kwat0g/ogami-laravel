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
        private readonly int $overtimeRequestId,
        private readonly int $employeeId,
        private readonly string $employeeName,
        private readonly string $workDate,
        private readonly int $requestedMinutes,
        /** Name of the supervisor who endorsed. */
        private readonly string $supervisorName,
        private readonly ?string $remarks = null,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(
        OvertimeRequest $request,
        string $supervisorName,
        ?string $remarks = null
    ): self {
        return new self(
            overtimeRequestId: $request->id,
            employeeId: $request->employee_id,
            employeeName: $request->employee->full_name,
            workDate: $request->work_date->toFormattedDateString(),
            requestedMinutes: $request->requested_minutes,
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
        $hours = round($this->requestedMinutes / 60, 2);

        return [
            'type' => 'overtime.supervisor_endorsed',
            'title' => 'Overtime Request Endorsed — Awaiting Your Approval',
            'message' => sprintf(
                '%s endorsed %s\'s overtime request for %s (%.2fh). The request is pending your final approval.%s',
                $this->supervisorName,
                $this->employeeName,
                $this->workDate,
                $hours,
                $this->remarks ? ' Supervisor remarks: '.$this->remarks : '',
            ),
            'action_url' => '/team/overtime',
            'overtime_request_id' => $this->overtimeRequestId,
            'employee_id' => $this->employeeId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
