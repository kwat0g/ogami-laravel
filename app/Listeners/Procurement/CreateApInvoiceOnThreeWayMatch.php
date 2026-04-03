<?php

declare(strict_types=1);

namespace App\Listeners\Procurement;

use App\Domains\AP\Services\VendorInvoiceService;
use App\Events\Procurement\ThreeWayMatchPassed;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sprint 4: Auto-create a draft AP invoice when a three-way match passes.
 *
 * The created invoice is a draft — the Accounting Officer must still
 * review, fill in the missing GL accounts, and submit for approval.
 * SOD-009 continues to apply when the Officer submits.
 *
 * REC-04: Added idempotency guard (checks ap_invoice_created flag),
 * critical-level logging on failure, and AP team notification when
 * auto-draft permanently fails so the GR does not silently lack an invoice.
 */
class CreateApInvoiceOnThreeWayMatch
{
    public function __construct(
        private readonly VendorInvoiceService $invoiceService,
    ) {}

    public function handle(ThreeWayMatchPassed $event): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->process($event));

            return;
        }

        $this->process($event);
    }

    private function process(ThreeWayMatchPassed $event): void
    {
        $gr = $event->goodsReceipt;

        // Idempotency: skip if AP invoice was already created for this GR
        if ($gr->ap_invoice_created) {
            Log::info('CreateApInvoiceOnThreeWayMatch: AP invoice already created for GR '.$gr->id.', skipping.');

            return;
        }

        Log::info('CreateApInvoiceOnThreeWayMatch handling GR: '.$gr->id);

        try {
            $invoice = $this->invoiceService->createFromPo(
                $gr,
                $gr->confirmed_by_id ?? $gr->received_by_id,
            );
            Log::info('Created Invoice: '.$invoice->id);
        } catch (\Throwable $e) {
            // Log at critical level — this means goods are received but no AP
            // liability is recorded. This is a GAAP violation for accrual accounting.
            Log::critical('Auto AP invoice creation failed after three-way match', [
                'gr_id' => $gr->id,
                'gr_reference' => $gr->gr_reference,
                'po_id' => $gr->purchase_order_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Notify AP team so they can manually create the invoice
            $this->notifyApTeamOfFailure($gr, $e);
        }
    }

    private function notifyApTeamOfFailure(mixed $gr, \Throwable $e): void
    {
        try {
            $apUsers = User::role('officer')
                ->get()
                ->filter(fn (User $u) => $u->hasPermissionTo('ap.create'));

            if ($apUsers->isEmpty()) {
                Log::warning('No AP officers found to notify about failed invoice auto-draft for GR '.$gr->id);

                return;
            }

            // Use database notification channel for reliability
            foreach ($apUsers as $user) {
                $user->notify(new \App\Notifications\Procurement\GrInvoiceDraftFailedNotification($gr, $e->getMessage()));
            }
        } catch (\Throwable $notifError) {
            // Notification failure must not mask the original error
            Log::error('Failed to notify AP team about invoice auto-draft failure', [
                'gr_id' => $gr->id,
                'notification_error' => $notifError->getMessage(),
            ]);
        }
    }
}
