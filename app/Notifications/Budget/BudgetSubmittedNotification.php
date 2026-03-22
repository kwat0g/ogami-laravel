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
        private readonly int $budgetId,
        private readonly string $costCenterName,
        private readonly string $fiscalYear,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(AnnualBudget $budget): self
    {
        return new self(
            budgetId: $budget->id,
            costCenterName: $budget->costCenter?->name ?? "CC-{$budget->cost_center_id}",
            fiscalYear: (string) ($budget->fiscal_year ?? ''),
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
            'type' => 'budget.submitted',
            'title' => 'Budget Submitted for Approval',
            'message' => sprintf(
                'Budget for "%s" (FY %s) has been submitted and requires your approval.',
                $this->costCenterName,
                $this->fiscalYear,
            ),
            'action_url' => '/budget',
            'budget_id' => $this->budgetId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
