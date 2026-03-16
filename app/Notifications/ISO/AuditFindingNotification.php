<?php

declare(strict_types=1);

namespace App\Notifications\ISO;

use App\Domains\ISO\Models\AuditFinding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class AuditFindingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AuditFinding $finding,
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
            'type' => 'iso.audit_finding',
            'title' => 'New Audit Finding Raised',
            'message' => sprintf(
                'Audit finding "%s" (Severity: %s) requires corrective action.',
                $this->finding->title ?? "Finding #{$this->finding->id}",
                $this->finding->severity ?? 'unspecified',
            ),
            'action_url' => "/iso/audits/{$this->finding->audit_id}",
            'finding_id' => $this->finding->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
