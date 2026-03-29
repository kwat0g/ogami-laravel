<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Payroll\Models\PayrollRun;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * REC-06: Notifies payroll officers when a payroll run has been stuck
 * at PROCESSING and was auto-transitioned to FAILED by the watchdog.
 */
final class PayrollProcessingStuckNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PayrollRun $run,
        private readonly int $minutesStuck,
    ) {}

    /**
     * @return self
     */
    public static function fromModel(PayrollRun $run, int $minutesStuck): self
    {
        return new self($run, $minutesStuck);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Payroll Run Processing Timeout',
            'message' => "Payroll run {$this->run->reference_no} was stuck at PROCESSING for {$this->minutesStuck} minutes "
                . 'and has been automatically marked as FAILED. You can retry computation from the Pre-Run Check step.',
            'type' => 'payroll_processing_stuck',
            'payroll_run_id' => $this->run->id,
            'reference_no' => $this->run->reference_no,
            'minutes_stuck' => $this->minutesStuck,
            'severity' => 'critical',
        ];
    }
}
