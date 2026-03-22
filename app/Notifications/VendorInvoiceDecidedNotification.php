<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\AP\Models\VendorInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the invoice submitter when their vendor invoice is approved or rejected.
 *
 * Decision values:
 *   'approved' → invoice was approved and posted to GL
 *   'rejected' → invoice was returned to draft with a rejection note
 */
final class VendorInvoiceDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $invoiceId,
        private readonly string $invoiceUlid,
        private readonly string $vendorName,
        private readonly int $netAmountCentavos,
        private readonly string $decision,
        private readonly ?string $rejectionNote = null,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(
        VendorInvoice $invoice,
        string $decision,
        ?string $rejectionNote = null
    ): self {
        return new self(
            invoiceId: $invoice->id,
            invoiceUlid: $invoice->ulid,
            vendorName: (string) $invoice->vendor->getAttribute('name'),
            netAmountCentavos: (int) round((float) $invoice->net_amount * 100),
            decision: $decision,
            rejectionNote: $rejectionNote,
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
        $amount = number_format($this->netAmountCentavos / 100, 2);

        if ($this->decision === 'rejected') {
            return [
                'type' => 'ap.invoice_rejected',
                'title' => 'Vendor Invoice Returned for Revision',
                'message' => sprintf(
                    'Your vendor invoice of ₱%s from %s was returned to draft.%s',
                    $amount,
                    $this->vendorName,
                    $this->rejectionNote ? ' Reason: '.$this->rejectionNote : '',
                ),
                'action_url' => "/ap/invoices/{$this->invoiceUlid}",
                'vendor_invoice_id' => $this->invoiceId,
            ];
        }

        return [
            'type' => 'ap.invoice_approved',
            'title' => 'Vendor Invoice Approved',
            'message' => sprintf(
                'Your vendor invoice of ₱%s from %s has been approved and posted to the general ledger.',
                $amount,
                $this->vendorName,
            ),
            'action_url' => "/ap/invoices/{$this->invoiceUlid}",
            'vendor_invoice_id' => $this->invoiceId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
