<?php

declare(strict_types=1);

namespace App\Notifications\Procurement;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * REC-04: Notifies AP officers when auto-draft of a vendor invoice fails
 * after a three-way match passes. This ensures the GR does not silently
 * lack a corresponding AP liability record.
 */
final class GrInvoiceDraftFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly mixed $goodsReceipt,
        private readonly string $errorMessage,
    ) {}

    /** Use database channel for reliability — not email which can also fail. */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'AP Invoice Auto-Draft Failed',
            'message' => "Auto-creation of AP invoice for GR {$this->goodsReceipt->gr_reference} failed. "
                . 'Please create the vendor invoice manually. '
                . "Error: {$this->errorMessage}",
            'type' => 'gr_invoice_draft_failed',
            'gr_id' => $this->goodsReceipt->id,
            'gr_reference' => $this->goodsReceipt->gr_reference,
            'po_id' => $this->goodsReceipt->purchase_order_id,
            'severity' => 'critical',
        ];
    }
}
