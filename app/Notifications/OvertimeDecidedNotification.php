<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Attendance\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the employee when their overtime request is approved or rejected.
 */
final class OvertimeDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $overtimeRequestId,
        private readonly string $workDate,
        private readonly int $requestedMinutes,
        private readonly ?int $approvedMinutes,
        /** approved | rejected */
        private readonly string $decision,
        private readonly ?string $remarks = null,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(
        OvertimeRequest $request,
        string $decision,
        ?string $remarks = null
    ): self {
        return new self(
            overtimeRequestId: $request->id,
            workDate: $request->work_date->toFormattedDateString(),
            requestedMinutes: $request->requested_minutes,
            approvedMinutes: $request->approved_minutes ?? null,
            decision: $decision,
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
        if ($this->decision === 'approved') {
            $approvedHours = round(($this->approvedMinutes ?? $this->requestedMinutes) / 60, 2);

            return [
                'type' => 'overtime.approved',
                'title' => 'Overtime Request Approved',
                'message' => sprintf(
                    'Your overtime request for %s has been approved for %s hour(s).%s',
                    $this->workDate,
                    $approvedHours,
                    $this->remarks ? ' Remarks: '.$this->remarks : '',
                ),
                'action_url' => '/me/overtime',
                'overtime_request_id' => $this->overtimeRequestId,
                'decision' => 'approved',
            ];
        }

        return [
            'type' => 'overtime.rejected',
            'title' => 'Overtime Request Not Approved',
            'message' => sprintf(
                'Your overtime request for %s has been rejected.%s',
                $this->workDate,
                $this->remarks ? ' Reason: '.$this->remarks : '',
            ),
            'action_url' => '/me/overtime',
            'overtime_request_id' => $this->overtimeRequestId,
            'decision' => 'rejected',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
