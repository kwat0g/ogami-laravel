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

    /** @var array<string,string> */
    private const STATUS_LABELS = [
        'submitted'      => 'Submitted for Approval',
        'noted'          => 'Noted by Department Head',
        'checked'        => 'Checked by Manager',
        'reviewed'       => 'Reviewed — Awaiting VP Approval',
        'approved'       => 'Approved',
        'rejected'       => 'Rejected',
        'cancelled'      => 'Cancelled',
        'converted_to_po'=> 'Converted to Purchase Order',
    ];

    // Store only scalar values — never serialize Eloquent models into queue jobs.
    // Storing models causes ModelNotFoundException when the record is soft-deleted
    // by the time the worker processes the job.
    private readonly string $statusLabel;

    public function __construct(
        private readonly int $purchaseRequestId,
        private readonly string $prReference,
        private readonly string $prUlid,
        private readonly string $status,
        private readonly ?string $actorName = null,
        private readonly ?string $comments = null,
    ) {
        $this->queue = 'notifications';
        $this->statusLabel = self::STATUS_LABELS[$status] ?? ucfirst($status);
    }

    /**
     * Convenience constructor — accepts the model but only stores scalars.
     */
    public static function fromModel(
        PurchaseRequest $pr,
        string $status,
        ?string $actorName = null,
        ?string $comments = null,
    ): self {
        return new self(
            purchaseRequestId: $pr->id,
            prReference: $pr->pr_reference,
            prUlid: $pr->ulid,
            status: $status,
            actorName: $actorName,
            comments: $comments,
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
        $message = sprintf(
            'Purchase Request %s has been %s',
            $this->prReference,
            $this->statusLabel,
        );

        if ($this->actorName) {
            $message .= " by {$this->actorName}";
        }

        if ($this->comments) {
            $message .= ". \"{$this->comments}\"";
        }

        return [
            'type'                => 'procurement.purchase_request.'.$this->status,
            'title'               => "PR {$this->prReference}: {$this->statusLabel}",
            'message'             => $message.'.',
            'action_url'          => "/procurement/purchase-requests/{$this->prUlid}",
            'purchase_request_id' => $this->purchaseRequestId,
            'pr_reference'        => $this->prReference,
            'status'              => $this->status,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
