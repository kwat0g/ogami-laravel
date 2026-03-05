<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Payroll\Models\PayrollRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the payroll initiator and the HR Manager when a payroll run is cancelled.
 */
final class PayrollCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PayrollRun $run,
        private readonly ?string $reason = null,
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
        return [
            'type' => 'payroll.cancelled',
            'title' => 'Payroll Run Cancelled',
            'message' => sprintf(
                'Payroll run %s (%s) has been cancelled.%s',
                $this->run->reference_no,
                $this->run->pay_period_label,
                $this->reason ? ' Reason: '.$this->reason : '',
            ),
            'action_url' => "/payroll/runs/{$this->run->ulid}",
            'payroll_run_id' => $this->run->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
