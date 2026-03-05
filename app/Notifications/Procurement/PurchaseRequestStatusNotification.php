<?php

declare(strict_types=1);

namespace App\Notifications\Procurement;

use App\Domains\Procurement\Models\PurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a Purchase Request changes status.
 *
 * Used for all PR workflow transitions:
 *   submitted → noted → checked → reviewed → approved → rejected / cancelled
 */
final class PurchaseRequestStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private readonly string $statusLabel;

    /** @var array<string,string> */
    private const STATUS_LABELS = [
        'submitted'      => 'Submitted for Approval',
        'noted'          => 'Noted by Department Head',
        'checked'        => 'Checked by Manager',
        'reviewed'       => 'Reviewed — Awaiting VP Approval',
        'approved'       => 'Approved',
        'rejected'       => 'Rejected',
        'cancelled'      => 'Cancelled',
        'converted_to_po' => 'Converted to Purchase Order',
    ];

    public function __construct(
        private readonly PurchaseRequest $purchaseRequest,
        private readonly string $status,
        private readonly string|null $actorName = null,
        private readonly string|null $comments = null,
    ) {
        $this->queue       = 'notifications';
        $this->statusLabel = self::STATUS_LABELS[$status] ?? ucfirst($status);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $pr = $this->purchaseRequest;

        $message = sprintf(
            'Purchase Request %s has been %s',
            $pr->pr_reference,
            $this->statusLabel,
        );

        if ($this->actorName) {
            $message .= " by {$this->actorName}";
        }

        if ($this->comments) {
            $message .= ". \"{$this->comments}\"";
        }

        return [
            'type'               => 'procurement.purchase_request.' . $this->status,
            'title'              => "PR {$pr->pr_reference}: {$this->statusLabel}",
            'message'            => $message . '.',
            'action_url'         => "/procurement/purchase-requests/{$pr->ulid}",
            'purchase_request_id' => $pr->id,
            'pr_reference'       => $pr->pr_reference,
            'status'             => $this->status,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
