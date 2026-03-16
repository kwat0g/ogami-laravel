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

    /**
     * @param  VendorInvoice  $invoice  The invoice triggering the alert
     * @param  string  $alertType  Either 'overdue' or 'due_soon'
     * @param  int  $days  Number of days overdue or until due
     */
    public function __construct(
        private readonly VendorInvoice $invoice,
        private readonly string $alertType,
        private readonly int $days
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
        /** @var string $vendorName */
        $vendorName = $this->invoice->vendor->name ?? 'Unknown Vendor';

        if ($this->alertType === self::TYPE_OVERDUE) {
            $title = 'AP Invoice Overdue';
            $message = sprintf(
                'Invoice %s from %s is %d day(s) overdue. Balance due: ₱%s',
                $this->invoice->invoice_number ?? 'N/A',
                $vendorName,
                $this->days,
                number_format((float) $this->invoice->balance_due, 2)
            );
        } else {
            $title = 'AP Invoice Due Soon';
            $message = sprintf(
                'Invoice %s from %s is due in %d day(s). Balance due: ₱%s',
                $this->invoice->invoice_number ?? 'N/A',
                $vendorName,
                $this->days,
                number_format((float) $this->invoice->balance_due, 2)
            );
        }

        return [
            'type' => 'ap.due_date_alert',
            'alert_type' => $this->alertType,
            'title' => $title,
            'message' => $message,
            'action_url' => "/ap/invoices/{$this->invoice->ulid}",
            'vendor_invoice_id' => $this->invoice->id,
            'vendor_id' => $this->invoice->vendor_id,
            'invoice_no' => $this->invoice->invoice_number,
            'vendor_name' => $vendorName,
            'due_date' => $this->invoice->due_date->toDateString(),
            'balance_due' => $this->invoice->balance_due,
            'days' => $this->days,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
