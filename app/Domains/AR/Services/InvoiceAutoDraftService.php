<?php

declare(strict_types=1);

namespace App\Domains\AR\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Shared\Contracts\ServiceContract;

/**
 * InvoiceAutoDraftService (AR)
 *
 * Automatically creates a draft customer (AR) invoice when a Delivery Receipt
 * status becomes 'delivered'. Pre-populates from the Client Order + DR data.
 *
 * Flow: DR delivered -> auto-draft AR invoice -> clerk reviews and approves
 *
 * The invoice is created as 'draft' status -- it still requires approval
 * (which assigns the invoice number INV-YYYY-MM-NNNNNN per AR-003).
 */
final class InvoiceAutoDraftService implements ServiceContract
{
    /**
     * Create a draft AR invoice from a delivered Delivery Receipt.
     *
     * Returns null if:
     * - DR is not in 'delivered' status
     * - An invoice already exists for this DR
     * - Required data (customer, fiscal period, GL accounts) is missing
     */
    public function createFromDeliveryReceipt(DeliveryReceipt $dr): ?CustomerInvoice
    {
        // Guard: only delivered DRs
        if ($dr->status !== 'delivered') {
            return null;
        }

        // Guard: don't create duplicate invoices
        $existing = CustomerInvoice::where('delivery_receipt_id', $dr->id)->first();
        if ($existing) {
            Log::info('[AR AutoDraft] Invoice already exists for DR', ['dr_id' => $dr->id]);
            return null;
        }

        // Find customer from DR or linked Client Order
        $customer = $dr->customer;
        if (! $customer instanceof Customer) {
            // Try to find from client order
            $clientOrder = $dr->clientOrder ?? ClientOrder::where('delivery_schedule_id', $dr->delivery_schedule_id)->first();
            if ($clientOrder) {
                $customer = $clientOrder->customer;
            }
        }

        if (! $customer instanceof Customer) {
            Log::warning('[AR AutoDraft] No customer found for DR', ['dr_id' => $dr->id]);
            return null;
        }

        // Calculate total from DR items
        $subtotalCentavos = 0;
        foreach ($dr->items as $drItem) {
            if ($drItem->condition === 'rejected') {
                continue;
            }

            $qty = (float) $drItem->quantity_received;

            // Get price from client order item or PO item
            $unitPrice = $this->getUnitPrice($drItem, $dr);
            $subtotalCentavos += (int) round($qty * $unitPrice * 100);
        }

        if ($subtotalCentavos <= 0) {
            Log::info('[AR AutoDraft] No billable items in DR', ['dr_id' => $dr->id]);
            return null;
        }

        return DB::transaction(function () use ($dr, $customer, $subtotalCentavos): ?CustomerInvoice {
            // Find current open fiscal period
            $fiscalPeriod = FiscalPeriod::where('status', 'open')
                ->where('date_from', '<=', now()->toDateString())
                ->where('date_to', '>=', now()->toDateString())
                ->first();

            // Find default AR and revenue accounts
            $arAccount = ChartOfAccount::where('account_code', 'LIKE', '1%') // Assets
                ->where('name', 'LIKE', '%accounts receivable%')
                ->first();

            $revenueAccount = ChartOfAccount::where('account_code', 'LIKE', '4%') // Revenue
                ->where('is_active', true)
                ->first();

            if (! $fiscalPeriod || ! $arAccount || ! $revenueAccount) {
                Log::warning('[AR AutoDraft] Missing fiscal period or GL accounts', [
                    'dr_id' => $dr->id,
                    'has_fiscal_period' => (bool) $fiscalPeriod,
                    'has_ar_account' => (bool) $arAccount,
                    'has_revenue_account' => (bool) $revenueAccount,
                ]);
                return null;
            }

            $subtotal = $subtotalCentavos / 100;
            $invoiceDate = now()->toDateString();
            $dueDate = now()->addDays(30)->toDateString(); // Standard 30-day terms

            // VAT: 12% if customer is VAT-applicable
            $vatAmount = $customer->is_vat_registered ? round($subtotal * 0.12, 2) : 0.00;

            $invoice = CustomerInvoice::create([
                'customer_id' => $customer->id,
                'fiscal_period_id' => $fiscalPeriod->id,
                'ar_account_id' => $arAccount->id,
                'revenue_account_id' => $revenueAccount->id,
                'delivery_receipt_id' => $dr->id,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total_amount' => $subtotal + $vatAmount,
                'balance_due' => $subtotal + $vatAmount,
                'description' => "Auto-drafted from Delivery Receipt {$dr->dr_reference}",
                'status' => 'draft',
                'created_by' => $dr->confirmed_by_id ?? $dr->received_by_id,
            ]);

            Log::info('[AR AutoDraft] Draft customer invoice created', [
                'invoice_id' => $invoice->id,
                'dr_id' => $dr->id,
                'customer_id' => $customer->id,
                'subtotal' => $subtotal,
            ]);

            return $invoice;
        });
    }

    /**
     * Get unit price for a DR item from the client order or a default.
     */
    private function getUnitPrice(mixed $drItem, DeliveryReceipt $dr): float
    {
        // Try client order item price
        if ($dr->clientOrder) {
            $orderItem = $dr->clientOrder->items
                ?->first(fn ($oi) => ($oi->item_master_id ?? $oi->product_item_id) === $drItem->item_master_id);

            if ($orderItem && (float) ($orderItem->agreed_unit_price ?? $orderItem->unit_price ?? 0) > 0) {
                return (float) ($orderItem->agreed_unit_price ?? $orderItem->unit_price);
            }
        }

        // Try PO item price
        if ($drItem->poItem) {
            return (float) ($drItem->poItem->unit_price ?? 0);
        }

        // Fallback: item standard cost
        if ($drItem->itemMaster) {
            return (float) ($drItem->itemMaster->standard_cost ?? 0);
        }

        return 0;
    }
}
