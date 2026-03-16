<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Payroll\Models\PayrollRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent to the Accounting Manager when HR submits a payroll run for approval.
 */
final class PayrollSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PayrollRun $run)
    {
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
            'type' => 'payroll.submitted',
            'title' => 'Payroll Awaiting Approval',
            'message' => sprintf(
                'Payroll run %s (%s) has been submitted for your approval. Pay date: %s.',
                $this->run->reference_no,
                $this->run->pay_period_label,
                $this->run->pay_date
                    ? Carbon::parse($this->run->pay_date)->toFormattedDateString()
                    : 'TBD',
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
