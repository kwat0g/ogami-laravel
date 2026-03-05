<?php

declare(strict_types=1);

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorInvoice;
use App\Domains\AP\Models\VendorPayment;
use App\Domains\AP\Services\ApPaymentPostingService;

/*
|--------------------------------------------------------------------------
| AP Payment → General Ledger Integration Tests
|--------------------------------------------------------------------------
| Verifies that:
|   1. Approving an AP invoice creates a balanced JE (Dr Expense / Cr AP)
|   2. Recording an AP payment creates a balanced JE (Dr AP / Cr Cash)
|   3. Combined flow: invoice + payment = net zero AP balance
--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'ChartOfAccountsSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'SystemSettingsSeeder'])->assertExitCode(0);

    $this->svc = app(ApPaymentPostingService::class);

    $this->user = \App\Models\User::firstOrCreate(
        ['email' => 'system-test@ogami.test'],
        ['name' => 'System Test', 'password' => bcrypt('test'), 'email_verified_at' => now()]
    );

    $this->fiscalPeriod = FiscalPeriod::firstOrCreate(
        ['name' => 'FY2025'],
        ['date_from' => '2025-01-01', 'date_to' => '2025-12-31', 'status' => 'open']
    );

    $this->expAcct = ChartOfAccount::where('code', '6001')->firstOrFail(); // Utilities Expense
    $this->apAcct = ChartOfAccount::where('code', '2001')->firstOrFail(); // Accounts Payable
    $this->cashAcct = ChartOfAccount::where('code', '1001')->firstOrFail(); // Cash in Bank

    $this->vendor = Vendor::firstOrCreate(
        ['name' => 'Test AP Vendor'],
        [
            'tin' => '123-456-789',
            'is_ewt_subject' => false,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]
    );
});

// ---------------------------------------------------------------------------
// INT-AP-GL-001: Approving an AP invoice → balanced JE (Dr Expense / Cr AP)
// ---------------------------------------------------------------------------

it('INT-AP-GL-001 — approving AP invoice creates Dr Expense / Cr AP entry', function () {
    $invoice = VendorInvoice::create([
        'vendor_id' => $this->vendor->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ap_account_id' => $this->apAcct->id,
        'expense_account_id' => $this->expAcct->id,
        'invoice_date' => '2025-09-30',
        'due_date' => '2025-10-30',
        'net_amount' => 25000.00,
        'status' => 'submitted',
        'created_by' => $this->user->id,
    ]);

    $je = $this->svc->postApInvoice($invoice);

    expect($je)->not->toBeNull();

    $totalDebit = (float) $je->lines()->sum('debit');
    $totalCredit = (float) $je->lines()->sum('credit');
    expect($totalDebit)->toEqual($totalCredit);

    // Specific accounts
    $codes = $je->lines()
        ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
        ->pluck('chart_of_accounts.code')
        ->toArray();

    expect($codes)->toContain('6001'); // expense debit
    expect($codes)->toContain('2001'); // AP payable credit
});

// ---------------------------------------------------------------------------
// INT-AP-GL-002: Recording AP payment → balanced JE (Dr AP / Cr Cash)
// ---------------------------------------------------------------------------

it('INT-AP-GL-002 — recording AP payment creates Dr AP / Cr Cash entry', function () {
    // Invoice needed so the payment service can resolve the AP account
    $invoice = VendorInvoice::create([
        'vendor_id' => $this->vendor->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ap_account_id' => $this->apAcct->id,
        'expense_account_id' => $this->expAcct->id,
        'invoice_date' => '2025-10-01',
        'due_date' => '2025-11-01',
        'net_amount' => 15000.00,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    $payment = VendorPayment::create([
        'vendor_invoice_id' => $invoice->id,
        'vendor_id' => $this->vendor->id,
        'payment_date' => '2025-10-28',
        'amount' => 15000.00,
        'payment_method' => 'bank_transfer',
        'created_by' => $this->user->id,
    ]);

    $je = $this->svc->postApPayment($payment);

    expect($je)->not->toBeNull();

    $totalDebit = (float) $je->lines()->sum('debit');
    $totalCredit = (float) $je->lines()->sum('credit');
    expect($totalDebit)->toEqual($totalCredit);

    $codes = $je->lines()
        ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
        ->pluck('chart_of_accounts.code')
        ->toArray();

    expect($codes)->toContain('2001'); // AP debit (clearing the liability)
    expect($codes)->toContain('1001'); // Cash credit
});

// ---------------------------------------------------------------------------
// INT-AP-GL-003: Full flow — invoice approval + payment nets to zero AP balance
// ---------------------------------------------------------------------------

it('INT-AP-GL-003 — invoice + payment leave AP account with net zero for this transaction', function () {
    $amount = 30000.00; // ₱30,000

    $invoice = VendorInvoice::create([
        'vendor_id' => $this->vendor->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ap_account_id' => $this->apAcct->id,
        'expense_account_id' => $this->expAcct->id,
        'invoice_date' => '2025-10-01',
        'due_date' => '2025-10-31',
        'net_amount' => $amount,
        'status' => 'submitted',
        'created_by' => $this->user->id,
    ]);

    $invoiceJe = $this->svc->postApInvoice($invoice);
    $invoice->update(['status' => 'approved']);

    $payment = VendorPayment::create([
        'vendor_invoice_id' => $invoice->id,
        'vendor_id' => $this->vendor->id,
        'payment_date' => '2025-10-31',
        'amount' => $amount,
        'payment_method' => 'bank_transfer',
        'created_by' => $this->user->id,
    ]);

    $paymentJe = $this->svc->postApPayment($payment);

    // Net AP movements: invoice Cr AP +30000, payment Dr AP +30000 → net = 0
    $allLines = $invoiceJe->lines->merge($paymentJe->lines);

    $netAP = $allLines
        ->where('account_id', $this->apAcct->id)
        ->sum(fn ($line) => (float) ($line->credit ?? 0) - (float) ($line->debit ?? 0));

    expect(round($netAP, 4))->toEqual(0.0);
});
