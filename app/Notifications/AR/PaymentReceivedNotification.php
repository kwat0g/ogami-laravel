<?php

declare(strict_types=1);

namespace App\Notifications\AR;

use App\Domains\AR\Models\CustomerInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to AR staff when a customer payment is received against an invoice.
 */
final class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $invoiceId,
        private readonly string $invoiceUlid,
        private readonly ?string $invoiceNumber,
        private readonly string $customerName,
        private readonly int $amountReceivedCentavos,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(CustomerInvoice $invoice, float $amountReceived): self
    {
        return new self(
            invoiceId: $invoice->id,
            invoiceUlid: $invoice->ulid,
            invoiceNumber: $invoice->invoice_number ?? null,
            customerName: (string) ($invoice->customer->company_name ?? "Customer #{$invoice->customer_id}"),
            amountReceivedCentavos: (int) round($amountReceived * 100),
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
        $amount = number_format($this->amountReceivedCentavos / 100, 2);
        $invoiceNo = $this->invoiceNumber ?? "INV-{$this->invoiceId}";

        return [
            'type' => 'ar.payment_received',
            'title' => 'Customer Payment Received',
            'message' => sprintf(
                'Payment of ₱%s received from %s for invoice %s.',
                $amount,
                $this->customerName,
                $invoiceNo,
            ),
            'action_url' => "/ar/invoices/{$this->invoiceUlid}",
            'customer_invoice_id' => $this->invoiceId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
