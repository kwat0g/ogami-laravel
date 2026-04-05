<?php

declare(strict_types=1);

namespace App\Listeners\AR;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AR\Services\CustomerInvoiceService;
use App\Domains\Delivery\Models\DeliveryDispute;
use App\Events\Delivery\ShipmentDelivered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

            $clientOrderId = $this->resolveClientOrderId($dr->id, $dr->delivery_schedule_id);

            if ($clientOrderId !== null) {
                $hasOpenDisputes = DeliveryDispute::query()
                    ->where('client_order_id', $clientOrderId)
                    ->whereIn('status', ['open', 'investigating', 'pending_resolution'])
                    ->exists();

                if ($hasOpenDisputes) {
                    Log::info('Auto AR invoice deferred — open delivery dispute(s) still active', [
                        'shipment_id' => $shipment->id,
                        'dr_id' => $dr->id,
                        'client_order_id' => $clientOrderId,
                    ]);

                    return;
                }
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

            if ($subtotal <= 0 && $clientOrderId !== null) {
                $orderTotalCentavos = DB::table('client_orders')
                    ->where('id', $clientOrderId)
                    ->value('total_amount_centavos');

                if ($orderTotalCentavos !== null && (int) $orderTotalCentavos > 0) {
                    $subtotal = round(((int) $orderTotalCentavos) / 100, 2);
                }
            }

            if ($subtotal <= 0) {
                Log::warning('Auto AR invoice skipped — computed subtotal is non-positive', [
                    'shipment_id' => $shipment->id,
                    'dr_id' => $dr->id,
                    'client_order_id' => $clientOrderId,
                ]);

                return;
            }

            $vatRate = 0.12; // Philippine standard VAT
            $vatAmount = $subtotal > 0 ? round($subtotal * $vatRate, 2) : 0.0;

            $description = $subtotal > 0
                ? "Auto-created from Shipment {$shipment->ulid}".($schedule !== null ? " / DS {$schedule->ds_reference}" : '')
                : "Auto-created from Shipment {$shipment->ulid} — unit price not set on delivery schedule. Update subtotal before approving.";

            $invoiceDate = $this->clampDateToFiscalPeriod($fiscalPeriod);
            $dueDate = Carbon::parse($invoiceDate)->addDays(30)->toDateString();

            $this->invoiceService->create(
                customer: $customer,
                data: [
                    'fiscal_period_id' => $fiscalPeriod->id,
                    'ar_account_id' => $arAccount?->id,
                    'revenue_account_id' => $revenueAccount?->id,
                    'invoice_date' => $invoiceDate,
                    'due_date' => $dueDate,
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

    private function resolveClientOrderId(int $deliveryReceiptId, ?int $deliveryScheduleId): ?int
    {
        if ($deliveryScheduleId !== null) {
            $orderId = DB::table('delivery_schedules')
                ->where('id', $deliveryScheduleId)
                ->value('client_order_id');

            if ($orderId !== null) {
                return (int) $orderId;
            }
        }

        $orderId = DB::table('delivery_schedules')
            ->where('delivery_receipt_id', $deliveryReceiptId)
            ->value('client_order_id');

        return $orderId !== null ? (int) $orderId : null;
    }

    private function clampDateToFiscalPeriod(FiscalPeriod $period): string
    {
        $today = now();
        $periodStart = Carbon::parse((string) $period->date_from);
        $periodEnd = Carbon::parse((string) $period->date_to);

        if ($today->lt($periodStart)) {
            return $periodStart->toDateString();
        }

        if ($today->gt($periodEnd)) {
            return $periodEnd->toDateString();
        }

        return $today->toDateString();
    }
}
