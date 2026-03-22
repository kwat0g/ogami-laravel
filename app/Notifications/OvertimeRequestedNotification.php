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

    public function __construct(
        private readonly int $overtimeRequestId,
        private readonly int $employeeId,
        private readonly string $employeeName,
        private readonly string $workDate,
        private readonly int $requestedMinutes,
        private readonly string $reason,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(OvertimeRequest $request): self
    {
        return new self(
            overtimeRequestId: $request->id,
            employeeId: $request->employee_id,
            employeeName: $request->employee->full_name,
            workDate: $request->work_date->toFormattedDateString(),
            requestedMinutes: $request->requested_minutes,
            reason: $request->reason,
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
            'type' => 'overtime.requested',
            'title' => 'New Overtime Request',
            'message' => sprintf(
                '%s has requested %s hour(s) of overtime on %s. Reason: %s',
                $this->employeeName,
                $hours,
                $this->workDate,
                $this->reason,
            ),
            'action_url' => '/hr/overtime',
            'overtime_request_id' => $this->overtimeRequestId,
            'employee_id' => $this->employeeId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
