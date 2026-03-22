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

    public function __construct(
        private readonly int $payrollRunId,
        private readonly string $payrollRunUlid,
        private readonly string $referenceNo,
        private readonly string $payPeriodLabel,
        private readonly ?string $payDate,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(PayrollRun $run): self
    {
        return new self(
            payrollRunId: $run->id,
            payrollRunUlid: $run->ulid,
            referenceNo: $run->reference_no,
            payPeriodLabel: $run->pay_period_label,
            payDate: $run->pay_date ? (string) $run->pay_date : null,
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
            'type' => 'payroll.submitted',
            'title' => 'Payroll Awaiting Approval',
            'message' => sprintf(
                'Payroll run %s (%s) has been submitted for your approval. Pay date: %s.',
                $this->referenceNo,
                $this->payPeriodLabel,
                $this->payDate
                    ? Carbon::parse($this->payDate)->toFormattedDateString()
                    : 'TBD',
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
