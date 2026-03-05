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
        private readonly OvertimeRequest $request,
        /** approved | rejected */
        private readonly string $decision,
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
        if ($this->decision === 'approved') {
            $approvedHours = round(($this->request->approved_minutes ?? $this->request->requested_minutes) / 60, 2);

            return [
                'type' => 'overtime.approved',
                'title' => 'Overtime Request Approved',
                'message' => sprintf(
                    'Your overtime request for %s has been approved for %s hour(s).%s',
                    $this->request->work_date->toFormattedDateString(),
                    $approvedHours,
                    $this->remarks ? ' Remarks: '.$this->remarks : '',
                ),
                'action_url' => '/me/overtime',
                'overtime_request_id' => $this->request->id,
                'decision' => 'approved',
            ];
        }

        return [
            'type' => 'overtime.rejected',
            'title' => 'Overtime Request Not Approved',
            'message' => sprintf(
                'Your overtime request for %s has been rejected.%s',
                $this->request->work_date->toFormattedDateString(),
                $this->remarks ? ' Reason: '.$this->remarks : '',
            ),
            'action_url' => '/me/overtime',
            'overtime_request_id' => $this->request->id,
            'decision' => 'rejected',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
