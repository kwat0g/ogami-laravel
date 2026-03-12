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
        private readonly CustomerInvoice $invoice,
        private readonly int $daysOverdue,
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
        $customerName = (string) ($this->invoice->customer->company_name ?? "Customer #{$this->invoice->customer_id}");
        $balance = number_format((float) $this->invoice->balance_due, 2);
        $invoiceNo = $this->invoice->invoice_number ?? "INV-{$this->invoice->id}";

        return [
            'type' => 'ar.invoice_overdue',
            'title' => 'Customer Invoice Overdue',
            'message' => sprintf(
                'Invoice %s from %s is %d days overdue. Outstanding balance: ₱%s.',
                $invoiceNo,
                $customerName,
                $this->daysOverdue,
                $balance,
            ),
            'action_url' => "/ar/invoices/{$this->invoice->ulid}",
            'customer_invoice_id' => $this->invoice->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
