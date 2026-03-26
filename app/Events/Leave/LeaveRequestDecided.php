<?php

declare(strict_types=1);

namespace App\Events\Leave;

use App\Domains\Leave\Models\LeaveRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a leave request is approved, rejected, or cancelled.
 *
 * Broadcast channel: private-user.{employee_user_id}
 * → The employee's browser receives this and shows a toast.
 */
final class LeaveRequestDecided implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LeaveRequest $request,
        /** User ID that owns the linked employee record. */
        public readonly int $employeeUserId,
        /** approved | rejected | cancelled */
        public readonly string $decision,
        public readonly ?string $remarks = null,
    ) {}

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->request->id,
            'decision' => $this->decision,
            'leave_type' => $this->request->leaveType?->name ?? $this->request->leave_type_id,
            'date_from' => $this->request->date_from?->toDateString(),
            'date_to' => $this->request->date_to?->toDateString(),
            'days' => $this->request->days_applied,
            'remarks' => $this->remarks,
        ];
    }

    public function broadcastAs(): string
    {
        return 'leave.decided';
    }

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->employeeUserId}")];
    }
}
