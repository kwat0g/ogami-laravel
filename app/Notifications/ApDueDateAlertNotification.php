<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\AP\Models\VendorInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * AP due date alert notification for overdue or upcoming invoices.
 *
 * Sent to accounting staff when invoices are overdue or due soon.
 */
final class ApDueDateAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE_OVERDUE = 'overdue';

    public const TYPE_DUE_SOON = 'due_soon';

    public function __construct(
        private readonly int $invoiceId,
        private readonly string $invoiceUlid,
        private readonly ?string $invoiceNumber,
        private readonly int $vendorId,
        private readonly string $vendorName,
        private readonly string $dueDate,
        private readonly int $balanceDueCentavos,
        private readonly string $alertType,
        private readonly int $days,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(VendorInvoice $invoice, string $alertType, int $days): self
    {
        return new self(
            invoiceId: $invoice->id,
            invoiceUlid: $invoice->ulid,
            invoiceNumber: $invoice->invoice_number ?? null,
            vendorId: $invoice->vendor_id,
            vendorName: (string) ($invoice->vendor->name ?? 'Unknown Vendor'),
            dueDate: $invoice->due_date->toDateString(),
            balanceDueCentavos: (int) round((float) $invoice->balance_due * 100),
            alertType: $alertType,
            days: $days,
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
        $balancePesos = number_format($this->balanceDueCentavos / 100, 2);

        if ($this->alertType === self::TYPE_OVERDUE) {
            $title = 'AP Invoice Overdue';
            $message = sprintf(
                'Invoice %s from %s is %d day(s) overdue. Balance due: ₱%s',
                $this->invoiceNumber ?? 'N/A',
                $this->vendorName,
                $this->days,
                $balancePesos
            );
        } else {
            $title = 'AP Invoice Due Soon';
            $message = sprintf(
                'Invoice %s from %s is due in %d day(s). Balance due: ₱%s',
                $this->invoiceNumber ?? 'N/A',
                $this->vendorName,
                $this->days,
                $balancePesos
            );
        }

        return [
            'type' => 'ap.due_date_alert',
            'alert_type' => $this->alertType,
            'title' => $title,
            'message' => $message,
            'action_url' => "/ap/invoices/{$this->invoiceUlid}",
            'vendor_invoice_id' => $this->invoiceId,
            'vendor_id' => $this->vendorId,
            'invoice_no' => $this->invoiceNumber,
            'vendor_name' => $this->vendorName,
            'due_date' => $this->dueDate,
            'balance_due' => $this->balanceDueCentavos / 100,
            'days' => $this->days,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
