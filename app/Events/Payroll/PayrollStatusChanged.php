<?php

declare(strict_types=1);

namespace App\Events\Payroll;

use App\Domains\Payroll\Models\PayrollRun;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Fired when a payroll run transitions to a significant status.
 *
 * Status transitions that fire this event:
 *   draft        → (no event)
 *   locked       → notify accounting dept channel
 *   completed    → notify HR dept + notify each employee individually (payslip ready)
 *   cancelled    → notify HR dept
 *
 * Broadcast channel: private-user.{targetUserId}
 */
final class PayrollStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PayrollRun $run,
        /** The user ID to send this broadcast to. */
        public readonly int $targetUserId,
        /** The new status of the run. */
        public readonly string $newStatus,
    ) {}

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->run->id,
            'reference_no' => $this->run->reference_no,
            'pay_period_label' => $this->run->pay_period_label,
            'status' => $this->newStatus,
            'pay_date' => $this->run->pay_date
                ? Carbon::parse($this->run->pay_date)->toDateString()
                : null,
        ];
    }

    public function broadcastAs(): string
    {
        return 'payroll.status_changed';
    }

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->targetUserId}")];
    }
}
