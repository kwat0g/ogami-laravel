<?php

declare(strict_types=1);

namespace App\Notifications\AR;

use App\Domains\AR\Models\CustomerInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to AR staff when a customer invoice is overdue.
 * Triggered by scheduled command or inline check.
 */
final class InvoiceOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $invoiceId,
        private readonly string $invoiceUlid,
        private readonly ?string $invoiceNumber,
        private readonly string $customerName,
        private readonly int $balanceDueCentavos,
        private readonly int $daysOverdue,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(CustomerInvoice $invoice, int $daysOverdue): self
    {
        return new self(
            invoiceId: $invoice->id,
            invoiceUlid: $invoice->ulid,
            invoiceNumber: $invoice->invoice_number ?? null,
            customerName: (string) ($invoice->customer->company_name ?? "Customer #{$invoice->customer_id}"),
            balanceDueCentavos: (int) round((float) $invoice->balance_due * 100),
            daysOverdue: $daysOverdue,
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
        $balance = number_format($this->balanceDueCentavos / 100, 2);
        $invoiceNo = $this->invoiceNumber ?? "INV-{$this->invoiceId}";

        return [
            'type' => 'ar.invoice_overdue',
            'title' => 'Customer Invoice Overdue',
            'message' => sprintf(
                'Invoice %s from %s is %d days overdue. Outstanding balance: ₱%s.',
                $invoiceNo,
                $this->customerName,
                $this->daysOverdue,
                $balance,
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
