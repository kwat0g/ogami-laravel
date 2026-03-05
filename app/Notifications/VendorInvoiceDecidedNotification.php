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
        private readonly VendorInvoice $invoice,
        private readonly string $decision,
        private readonly ?string $rejectionNote = null,
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
        $vendorName = (string) $this->invoice->vendor->getAttribute('name');
        $amount = number_format((float) $this->invoice->net_amount, 2);

        if ($this->decision === 'rejected') {
            return [
                'type' => 'ap.invoice_rejected',
                'title' => 'Vendor Invoice Returned for Revision',
                'message' => sprintf(
                    'Your vendor invoice of ₱%s from %s was returned to draft.%s',
                    $amount,
                    $vendorName,
                    $this->rejectionNote ? ' Reason: '.$this->rejectionNote : '',
                ),
                'action_url' => "/ap/invoices/{$this->invoice->ulid}",
                'vendor_invoice_id' => $this->invoice->id,
            ];
        }

        return [
            'type' => 'ap.invoice_approved',
            'title' => 'Vendor Invoice Approved',
            'message' => sprintf(
                'Your vendor invoice of ₱%s from %s has been approved and posted to the general ledger.',
                $amount,
                $vendorName,
            ),
            'action_url' => "/ap/invoices/{$this->invoice->ulid}",
            'vendor_invoice_id' => $this->invoice->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
