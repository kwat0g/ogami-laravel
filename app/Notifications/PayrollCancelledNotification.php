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
        private readonly int $payrollRunId,
        private readonly string $payrollRunUlid,
        private readonly string $referenceNo,
        private readonly string $payPeriodLabel,
        private readonly ?string $reason = null,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(PayrollRun $run, ?string $reason = null): self
    {
        return new self(
            payrollRunId: $run->id,
            payrollRunUlid: $run->ulid,
            referenceNo: $run->reference_no,
            payPeriodLabel: $run->pay_period_label,
            reason: $reason,
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
            'type' => 'payroll.cancelled',
            'title' => 'Payroll Run Cancelled',
            'message' => sprintf(
                'Payroll run %s (%s) has been cancelled.%s',
                $this->referenceNo,
                $this->payPeriodLabel,
                $this->reason ? ' Reason: '.$this->reason : '',
            ),
            'action_url' => "/payroll/runs/{$this->payrollRunUlid}",
            'payroll_run_id' => $this->payrollRunId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
