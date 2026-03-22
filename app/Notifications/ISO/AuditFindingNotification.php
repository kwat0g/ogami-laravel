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
        private readonly int $findingId,
        private readonly int $auditId,
        private readonly string $title,
        private readonly string $severity,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(AuditFinding $finding): self
    {
        return new self(
            findingId: $finding->id,
            auditId: $finding->audit_id,
            title: $finding->description ?? "Finding #{$finding->id}",
            severity: $finding->severity ?? 'unspecified',
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
            'type' => 'iso.audit_finding',
            'title' => 'New Audit Finding Raised',
            'message' => sprintf(
                'Audit finding "%s" (Severity: %s) requires corrective action.',
                $this->title,
                $this->severity,
            ),
            'action_url' => "/iso/audits/{$this->auditId}",
            'finding_id' => $this->findingId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
