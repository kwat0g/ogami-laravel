<?php

declare(strict_types=1);

namespace App\Notifications\Budget;

use App\Domains\Budget\Models\AnnualBudget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class BudgetSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AnnualBudget $budget,
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
            'type' => 'budget.submitted',
            'title' => 'Budget Submitted for Approval',
            'message' => sprintf(
                'Budget for "%s" (FY %s) has been submitted and requires your approval.',
                $this->budget->costCenter?->name ?? "CC-{$this->budget->cost_center_id}",
                $this->budget->fiscal_year ?? '',
            ),
            'action_url' => '/budget',
            'budget_id' => $this->budget->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
