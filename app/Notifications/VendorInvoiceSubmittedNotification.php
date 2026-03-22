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

    public function __construct(
        private readonly int $invoiceId,
        private readonly string $invoiceUlid,
        private readonly int $vendorId,
        private readonly string $vendorName,
        private readonly int $netAmountCentavos,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(VendorInvoice $invoice): self
    {
        return new self(
            invoiceId: $invoice->id,
            invoiceUlid: $invoice->ulid,
            vendorId: $invoice->vendor_id,
            vendorName: (string) $invoice->vendor->getAttribute('name'),
            netAmountCentavos: (int) round((float) $invoice->net_amount * 100),
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
            'type' => 'ap.invoice_submitted',
            'title' => 'Vendor Invoice Pending Approval',
            'message' => sprintf(
                'A vendor invoice of ₱%s from %s has been submitted for your review.',
                number_format($this->netAmountCentavos / 100, 2),
                $this->vendorName,
            ),
            'action_url' => "/ap/invoices/{$this->invoiceUlid}",
            'vendor_invoice_id' => $this->invoiceId,
            'vendor_id' => $this->vendorId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
