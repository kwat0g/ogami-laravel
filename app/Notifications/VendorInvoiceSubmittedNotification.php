<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\AP\Models\VendorInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to users with the `vendor_invoices.approve` permission when a vendor
 * invoice is submitted and is pending their review.
 */
final class VendorInvoiceSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly VendorInvoice $invoice)
    {
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
            'type' => 'ap.invoice_submitted',
            'title' => 'Vendor Invoice Pending Approval',
            'message' => sprintf(
                'A vendor invoice of ₱%s from %s has been submitted for your review.',
                number_format((float) $this->invoice->net_amount, 2),
                $this->invoice->vendor->getAttribute('name'),
            ),
            'action_url' => "/ap/invoices/{$this->invoice->ulid}",
            'vendor_invoice_id' => $this->invoice->id,
            'vendor_id' => $this->invoice->vendor_id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
