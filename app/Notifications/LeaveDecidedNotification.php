<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Leave\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the employee when their leave request is approved, rejected, or cancelled.
 */
final class LeaveDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $leaveRequestId,
        private readonly string $leaveTypeName,
        private readonly string $dateFrom,
        private readonly string $dateTo,
        /** approved | rejected | cancelled */
        private readonly string $decision,
        private readonly ?string $remarks = null,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(
        LeaveRequest $request,
        string $decision,
        ?string $remarks = null
    ): self {
        return new self(
            leaveRequestId: $request->id,
            leaveTypeName: $request->leaveType?->name ?? 'leave',
            dateFrom: $request->date_from?->toFormattedDateString() ?? '',
            dateTo: $request->date_to?->toFormattedDateString() ?? '',
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
        $verb = match ($this->decision) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            default => $this->decision,
        };

        return [
            'type' => 'leave.decided',
            'title' => 'Leave Request '.ucfirst($verb),
            'message' => sprintf(
                'Your %s leave request (%s – %s) has been %s.%s',
                $this->leaveTypeName,
                $this->dateFrom,
                $this->dateTo,
                $verb,
                $this->remarks ? ' Remarks: '.$this->remarks : '',
            ),
            'action_url' => '/me/leaves',
            'leave_request_id' => $this->leaveRequestId,
            'decision' => $this->decision,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
