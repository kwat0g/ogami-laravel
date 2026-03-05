<?php

declare(strict_types=1);

namespace App\Events\Leave;

use App\Domains\Leave\Models\LeaveRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an employee submits a leave request.
 *
 * Broadcast channel: private-user.{manager_user_id}
 * → The department manager's browser receives this and shows a real-time alert.
 */
final class LeaveRequestFiled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LeaveRequest $request,
        /** User ID of the manager who should be notified. */
        public readonly int $managerUserId,
    ) {}

    /** @return array{id:int,employee_name:string,leave_type:string,date_from:string,date_to:string,days:float} */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->request->id,
            'employee_name' => $this->request->employee?->full_name ?? 'Unknown',
            'leave_type' => $this->request->leaveType?->name ?? $this->request->leave_type_id,
            'date_from' => $this->request->date_from?->toDateString(),
            'date_to' => $this->request->date_to?->toDateString(),
            'days' => $this->request->days_applied,
        ];
    }

    public function broadcastAs(): string
    {
        return 'leave.filed';
    }

    /** @return \Illuminate\Broadcasting\Channel[] */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->managerUserId}")];
    }
}
