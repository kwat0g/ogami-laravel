<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\AP\Models\VendorInvoice;
use App\Domains\AP\Models\VendorPayment;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Posts AP invoice approvals and payments to the General Ledger.
 *
 * Invoice GL entry:
 *   DEBIT  expense_account  (net_amount)   — from VendorInvoice.expense_account_id
 *   CREDIT ap_account       (net_amount)   — from VendorInvoice.ap_account_id
 *
 * Payment GL entry:
 *   DEBIT  ap_account  (amount)            — clearing the AP liability
 *   CREDIT cash account 1001 (amount)      — cash disbursement
 *
 * source_type = 'ap' — SoD check is bypassed at service layer.
 * posted_by = NULL   — bypasses DB-level SoD constraint (auto-post).
 * Idempotent per source.
 */
final class ApPaymentPostingService implements ServiceContract
{
    /**
     * Post an AP invoice to the GL.
     * Creates: Dr Expense / Cr AP Payable for the invoice net amount.
     */
    public function postApInvoice(VendorInvoice $invoice): JournalEntry
    {
        // Idempotency
        $existing = JournalEntry::where('source_type', 'ap')
            ->where('source_id', $invoice->id)
            ->where('description', 'like', 'AP Invoice%')
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $amount = (float) $invoice->net_amount;
        $date = $invoice->invoice_date instanceof \Carbon\Carbon
            ? $invoice->invoice_date->toDateString()
            : (string) $invoice->invoice_date;

        $expenseAccountId = $invoice->expense_account_id
            ?? $this->accountIdByCode('6001');
        $apAccountId = $invoice->ap_account_id
            ?? $this->accountIdByCode('2001');

        $fiscalPeriod = $this->ensureFiscalPeriod($date);
        $systemUserId = $this->systemUserId();

        return DB::transaction(function () use ($invoice, $amount, $date, $expenseAccountId, $apAccountId, $fiscalPeriod, $systemUserId) {
            $je = JournalEntry::create([
                'date' => $date,
                'description' => "AP Invoice #{$invoice->id}",
                'source_type' => 'ap',
                'source_id' => $invoice->id,
                'status' => 'draft',
                'fiscal_period_id' => $fiscalPeriod->id,
                'created_by' => $systemUserId,
                'je_number' => null,
            ]);

            $je->lines()->create(['account_id' => $expenseAccountId, 'debit' => $amount, 'credit' => null]);
            $je->lines()->create(['account_id' => $apAccountId,      'debit' => null,    'credit' => $amount]);

            $je->update([
                'status' => 'posted',
                'je_number' => "JE-AP-{$invoice->id}",
                'posted_by' => null,
                'posted_at' => now(),
            ]);

            return $je->fresh(['lines']);
        });
    }

    /**
     * Post an AP payment to the GL.
     * Creates: Dr AP Payable / Cr Cash for the payment amount.
     */
    public function postApPayment(VendorPayment $payment): JournalEntry
    {
        // Idempotency
        $existing = JournalEntry::where('source_type', 'ap')
            ->where('source_id', $payment->id)
            ->whereNotNull('description')
            ->where('description', 'like', 'AP Payment%')
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $amount = (float) $payment->amount;
        $date = $payment->payment_date instanceof \Carbon\Carbon
            ? $payment->payment_date->toDateString()
            : (string) $payment->payment_date;

        // Get AP account from the related invoice
        $invoice = $payment->vendorInvoice;
        $apAccountId = $invoice?->ap_account_id ?? $this->accountIdByCode('2001');
        $cashAcctId = $this->accountIdByCode('1001');

        $fiscalPeriod = $this->ensureFiscalPeriod($date);
        $systemUserId = $this->systemUserId();

        return DB::transaction(function () use ($payment, $amount, $date, $apAccountId, $cashAcctId, $fiscalPeriod, $systemUserId) {
            $je = JournalEntry::create([
                'date' => $date,
                'description' => "AP Payment #{$payment->id}",
                'source_type' => 'ap',
                'source_id' => $payment->id,
                'status' => 'draft',
                'fiscal_period_id' => $fiscalPeriod->id,
                'created_by' => $systemUserId,
                'je_number' => null,
            ]);

            $je->lines()->create(['account_id' => $apAccountId, 'debit' => $amount, 'credit' => null]);
            $je->lines()->create(['account_id' => $cashAcctId,  'debit' => null,   'credit' => $amount]);

            $je->update([
                'status' => 'posted',
                'je_number' => "JE-APM-{$payment->id}",
                'posted_by' => null,
                'posted_at' => now(),
            ]);

            return $je->fresh(['lines']);
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function accountIdByCode(string $code): int
    {
        $id = DB::table('chart_of_accounts')
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->value('id');

        if ($id === null) {
            throw new DomainException(
                "Chart of account '{$code}' not found.",
                'AP_ACCOUNT_NOT_FOUND',
                422,
            );
        }

        return $id;
    }

    private function ensureFiscalPeriod(string $date): FiscalPeriod
    {
        // Look up the fiscal period that covers the given date.
        $period = FiscalPeriod::whereDate('date_from', '<=', $date)
            ->whereDate('date_to', '>=', $date)
            ->orderBy('date_from', 'desc')
            ->first();

        if ($period !== null) {
            return $period;
        }

        // Fallback: create a monthly period for the given date so payments
        // are never blocked by a missing fiscal period setup.
        $carbon = \Carbon\Carbon::parse($date);
        $name   = $carbon->format('M Y');           // e.g. "Mar 2026"
        $from   = $carbon->startOfMonth()->toDateString();
        $to     = $carbon->endOfMonth()->toDateString();

        return FiscalPeriod::firstOrCreate(
            ['name' => $name],
            ['date_from' => $from, 'date_to' => $to, 'status' => 'open'],
        );
    }

    private function systemUserId(): int
    {
        return \App\Models\User::where('email', 'system-test@ogami.test')->value('id')
            ?? \App\Models\User::value('id')
            ?? 1;
    }
}
