<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorInvoice;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * InvoiceAutoDraftService
 *
 * Automatically creates a draft AP invoice when a Goods Receipt is confirmed.
 * Pre-populates all fields from the PO + GR data so the AP clerk only needs
 * to review and submit (instead of manually creating from scratch).
 *
 * Flow: GR confirmed -> 3-way match -> auto-draft AP invoice
 *
 * The invoice is created as 'draft' status -- it still requires the full
 * approval workflow (submit -> head_note -> manager_check -> officer_review -> approve).
 */
final class InvoiceAutoDraftService
{
    public function __construct(
        private readonly EwtService $ewtService,
    ) {}

    /**
     * Create a draft AP invoice from a confirmed Goods Receipt.
     *
     * Returns null if invoice was already created for this GR,
     * or if required data is missing (no vendor, no PO, etc.).
     */
    public function createFromGoodsReceipt(GoodsReceipt $gr): ?VendorInvoice
    {
        // Guard: only confirmed GRs
        if ($gr->status !== 'confirmed') {
            return null;
        }

        // Guard: don't create duplicate invoices
        if ($gr->ap_invoice_created) {
            return null;
        }

        $po = $gr->purchaseOrder;
        if (! $po instanceof PurchaseOrder) {
            Log::warning('[InvoiceAutoDraft] GR has no linked PO', ['gr_id' => $gr->id]);
            return null;
        }

        $vendor = $po->vendor;
        if (! $vendor instanceof Vendor) {
            Log::warning('[InvoiceAutoDraft] PO has no vendor', ['po_id' => $po->id]);
            return null;
        }

        // Calculate total from GR items (accepted items only, excluding rejected)
        $netAmountCentavos = 0;
        foreach ($gr->items as $grItem) {
            if ($grItem->condition === 'rejected') {
                continue;
            }

            $poItem = $grItem->poItem;
            if (! $poItem) {
                continue;
            }

            $qty = (float) $grItem->quantity_received;
            $unitPrice = (float) $poItem->unit_price;
            $netAmountCentavos += (int) round($qty * $unitPrice * 100);
        }

        if ($netAmountCentavos <= 0) {
            Log::info('[InvoiceAutoDraft] No billable items in GR', ['gr_id' => $gr->id]);
            return null;
        }

        return DB::transaction(function () use ($gr, $po, $vendor, $netAmountCentavos): VendorInvoice {
            // Find current fiscal period
            $fiscalPeriod = FiscalPeriod::where('status', 'open')
                ->where('date_from', '<=', now()->toDateString())
                ->where('date_to', '>=', now()->toDateString())
                ->first();

            // Find default AP and expense accounts
            $apAccount = ChartOfAccount::where('account_code', 'LIKE', '2%') // Liabilities
                ->where('name', 'LIKE', '%accounts payable%')
                ->first();

            $expenseAccount = ChartOfAccount::where('account_code', 'LIKE', '5%') // Expenses
                ->where('is_active', true)
                ->first();

            if (! $fiscalPeriod || ! $apAccount || ! $expenseAccount) {
                Log::warning('[InvoiceAutoDraft] Missing fiscal period or GL accounts', [
                    'gr_id' => $gr->id,
                    'has_fiscal_period' => (bool) $fiscalPeriod,
                    'has_ap_account' => (bool) $apAccount,
                    'has_expense_account' => (bool) $expenseAccount,
                ]);
                return null;
            }

            $netAmount = $netAmountCentavos / 100;
            $invoiceDate = now()->toDateString();

            // Standard payment terms: 30 days from invoice
            $dueDate = now()->addDays(30)->toDateString();

            // Compute VAT (12% if vendor is VAT-registered)
            $vatAmount = $vendor->is_vat_registered ? round($netAmount * 0.12, 2) : 0.00;

            // Compute EWT
            $ewtAmount = $this->ewtService->computeForInvoice(
                vendor: $vendor,
                netAmount: $netAmount,
                invoiceDate: now(),
            );

            $invoice = VendorInvoice::create([
                'vendor_id' => $vendor->id,
                'fiscal_period_id' => $fiscalPeriod->id,
                'ap_account_id' => $apAccount->id,
                'expense_account_id' => $expenseAccount->id,
                'purchase_order_id' => $po->id,
                'goods_receipt_id' => $gr->id,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'net_amount' => $netAmount,
                'vat_amount' => $vatAmount,
                'ewt_amount' => $ewtAmount,
                'ewt_rate' => $vendor->is_ewt_subject ? $vendor->ewtRate?->rate : null,
                'atc_code' => $vendor->is_ewt_subject ? $vendor->atc_code : null,
                'description' => "Auto-drafted from GR {$gr->gr_reference} / PO {$po->po_reference}",
                'status' => 'draft',
                'created_by' => $gr->confirmed_by_id,
            ]);

            // Mark GR as having an AP invoice created
            $gr->update(['ap_invoice_created' => true]);

            Log::info('[InvoiceAutoDraft] Draft AP invoice created', [
                'invoice_id' => $invoice->id,
                'gr_id' => $gr->id,
                'po_id' => $po->id,
                'net_amount' => $netAmount,
            ]);

            return $invoice;
        });
    }
}
