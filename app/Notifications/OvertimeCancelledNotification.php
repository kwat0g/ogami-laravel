<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Attendance\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the direct supervisor (or dept manager fallback) when an employee
 * cancels their pending overtime request. Lets the reviewer know no action
 * is required on their end.
 */
final class OvertimeCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $overtimeRequestId,
        private readonly int $employeeId,
        private readonly string $employeeName,
        private readonly string $workDate,
        private readonly int $requestedMinutes,
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
            'type' => 'overtime.cancelled',
            'title' => 'Overtime Request Cancelled',
            'message' => sprintf(
                '%s has cancelled their overtime request for %s (%d min). No further action required.',
                $this->employeeName,
                $this->workDate,
                $this->requestedMinutes,
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
