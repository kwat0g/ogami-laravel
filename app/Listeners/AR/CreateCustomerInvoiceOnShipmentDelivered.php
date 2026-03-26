<?php

declare(strict_types=1);

namespace App\Listeners\AR;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AR\Services\CustomerInvoiceService;
use App\Events\Delivery\ShipmentDelivered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Auto-create a draft Customer Invoice when a Shipment is marked delivered.
 *
 * Symmetric to CreateApInvoiceOnThreeWayMatch on the AP side.
 * The created invoice is a draft — finance staff must still review
 * subtotal, VAT, and approve before GL is posted.
 *
 * Subtotal is derived from: delivery_schedule.unit_price × qty_ordered
 * If unit_price is null, invoice is created with subtotal = 0 and a
 * reminder description so finance can fill in the amount manually.
 */
final class CreateCustomerInvoiceOnShipmentDelivered implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly CustomerInvoiceService $invoiceService,
    ) {}

    public function handle(ShipmentDelivered $event): void
    {
        $shipment = $event->shipment;

        // Idempotency guard — mirrors ap_invoice_created on GRs
        if ($shipment->ar_invoice_created) {
            return;
        }

        try {
            $shipment->loadMissing(['deliveryReceipt.customer', 'deliveryReceipt.deliverySchedule']);

            $dr = $shipment->deliveryReceipt;

            if ($dr === null) {
                Log::warning('Auto AR invoice skipped — shipment has no delivery receipt', [
                    'shipment_id' => $shipment->id,
                ]);

                return;
            }

            $customer = $dr->customer;

            if ($customer === null) {
                Log::warning('Auto AR invoice skipped — delivery receipt has no customer (inbound or unlinked)', [
                    'shipment_id' => $shipment->id,
                    'dr_id' => $dr->id,
                ]);

                return;
            }

            // Resolve fiscal period — most recent open period
            $fiscalPeriod = FiscalPeriod::open()->latest('date_from')->first();

            if ($fiscalPeriod === null) {
                Log::error('Auto AR invoice skipped — no open fiscal period', ['shipment_id' => $shipment->id]);

                return;
            }

            // Resolve GL accounts by standard code (seeded)
            $arAccount = ChartOfAccount::where('code', '3001')->first();
            $revenueAccount = ChartOfAccount::where('code', '4001')->first();

            // Compute subtotal from delivery schedule if available
            $schedule = $dr->deliverySchedule;
            $subtotal = 0.0;

            if ($schedule !== null && $schedule->unit_price !== null && $schedule->qty_ordered > 0) {
                $subtotal = round((float) $schedule->unit_price * (float) $schedule->qty_ordered, 2);
            }

            $vatRate = 0.12; // Philippine standard VAT
            $vatAmount = $subtotal > 0 ? round($subtotal * $vatRate, 2) : 0.0;

            $description = $subtotal > 0
                ? "Auto-created from Shipment {$shipment->ulid}".($schedule !== null ? " / DS {$schedule->ds_reference}" : '')
                : "Auto-created from Shipment {$shipment->ulid} — unit price not set on delivery schedule. Update subtotal before approving.";

            $this->invoiceService->create(
                customer: $customer,
                data: [
                    'fiscal_period_id' => $fiscalPeriod->id,
                    'ar_account_id' => $arAccount?->id,
                    'revenue_account_id' => $revenueAccount?->id,
                    'invoice_date' => now()->toDateString(),
                    'due_date' => now()->addDays(30)->toDateString(),
                    'subtotal' => $subtotal,
                    'vat_amount' => $vatAmount,
                    'description' => $description,
                    'bypass_credit_check' => true,
                ],
                userId: 1, // system actor
            );

            // Mark idempotency flag
            $shipment->update(['ar_invoice_created' => true]);
        } catch (\Throwable $e) {
            // Log but do not re-throw — shipment delivery must not roll back due to AR failure.
            Log::error('Auto AR invoice creation failed after shipment delivered', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
