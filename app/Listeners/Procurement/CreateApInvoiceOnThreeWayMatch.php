<?php

declare(strict_types=1);

namespace App\Listeners\Procurement;

use App\Domains\AP\Services\VendorInvoiceService;
use App\Events\Procurement\ThreeWayMatchPassed;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 4: Auto-create a draft AP invoice when a three-way match passes.
 *
 * The created invoice is a draft — the Accounting Officer must still
 * review, fill in the missing GL accounts, and submit for approval.
 * SOD-009 continues to apply when the Officer submits.
 */
class CreateApInvoiceOnThreeWayMatch
{
    public function __construct(
        private readonly VendorInvoiceService $invoiceService,
    ) {}

    public function handle(ThreeWayMatchPassed $event): void
    {
        $gr = $event->goodsReceipt;
        Log::info('CreateApInvoiceOnThreeWayMatch handling GR: '.$gr->id);

        try {
            $invoice = $this->invoiceService->createFromPo(
                $gr,
                $gr->confirmed_by_id ?? $gr->received_by_id,
            );
            Log::info('Created Invoice: '.$invoice->id);
        } catch (\Throwable $e) {
            // Log but do not re-throw — GR confirmation must not roll back due to AP failure.
            Log::error('Auto AP invoice creation failed after three-way match', [
                'gr_id' => $gr->id,
                'gr_reference' => $gr->gr_reference,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
