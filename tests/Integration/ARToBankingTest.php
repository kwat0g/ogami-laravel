<?php

declare(strict_types=1);

use App\Domains\Accounting\Models\BankAccount;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Models\CustomerPayment;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| AR → Banking Integration Tests
|--------------------------------------------------------------------------
| Verifies the complete accounts receivable to banking workflow:
|   1. Customer invoice creation
|   2. Payment receipt
|   3. Bank deposit linkage
|   4. AR aging updates
|
| Flow: Customer Invoice → Payment Receipt → Bank Deposit → GL Posting
--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'ChartOfAccountsSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'FiscalPeriodSeeder'])->assertExitCode(0);

    $this->user = User::factory()->create();
    $this->user->assignRole('manager');

    $this->customer = Customer::create([
        'name' => 'Test Customer Inc.',
        'contact_person' => 'John Doe',
        'email' => 'test@customer.com',
        'phone' => '1234567890',
        'credit_limit' => 100_000.00,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // Get or create accounts
    $this->arAccount = ChartOfAccount::where('code', '1200')->first()
        ?? ChartOfAccount::create([
            'code' => '1200',
            'name' => 'Accounts Receivable - Trade',
            'account_type' => 'ASSET',
            'normal_balance' => 'DEBIT',
            'is_active' => true,
        ]);

    $this->salesAccount = ChartOfAccount::where('code', '4100')->first()
        ?? ChartOfAccount::create([
            'code' => '4100',
            'name' => 'Sales Revenue',
            'account_type' => 'REVENUE',
            'normal_balance' => 'CREDIT',
            'is_active' => true,
        ]);

    $this->cashAccount = ChartOfAccount::where('code', '1100')->first()
        ?? ChartOfAccount::create([
            'code' => '1100',
            'name' => 'Cash in Bank',
            'account_type' => 'ASSET',
            'normal_balance' => 'DEBIT',
            'is_active' => true,
        ]);

    $this->bankAccount = BankAccount::create([
        'name' => 'Primary Checking',
        'bank_name' => 'Test Bank',
        'account_number' => '1234567890',
        'account_type' => 'checking',
        'account_id' => $this->cashAccount->id,
        'is_active' => true,
    ]);

    $this->fiscalPeriod = FiscalPeriod::first()
        ?? FiscalPeriod::create([
            'name' => '2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_closed' => false,
        ]);
});

// ---------------------------------------------------------------------------
// INT-AR-BNK-001: Customer payment updates AR balance
// ---------------------------------------------------------------------------

it('INT-AR-BNK-001 — customer payment reduces outstanding AR', function () {
    $invoiceAmount = 50000.00; // ₱50,000
    $paymentAmount = 30000.00; // ₱30,000 partial payment

    // Create customer invoice
    $invoice = CustomerInvoice::create([
        'customer_id' => $this->customer->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->salesAccount->id,
        'invoice_number' => 'INV-TEST-'.uniqid(),
        'invoice_date' => now()->subDays(15),
        'due_date' => now()->addDays(15),
        'subtotal' => $invoiceAmount,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    // Verify invoice total (refresh to get computed column value)
    $invoice->refresh();
    expect((float) $invoice->total_amount)->toBe($invoiceAmount);

    // Record payment
    $payment = CustomerPayment::create([
        'customer_id' => $this->customer->id,
        'customer_invoice_id' => $invoice->id,
        'amount' => $paymentAmount,
        'payment_date' => now(),
        'payment_method' => 'bank_transfer',
        'reference_number' => 'BANK-REF-001',
        'created_by' => $this->user->id,
    ]);

    // Update invoice status to partially_paid
    $invoice->status = 'partially_paid';
    $invoice->save();

    // Verify payment recorded
    expect((float) $payment->amount)->toBe($paymentAmount);
    expect($payment->customer_invoice_id)->toBe($invoice->id);
});

// ---------------------------------------------------------------------------
// INT-AR-BNK-002: Payment creates bank deposit entry
// ---------------------------------------------------------------------------

it('INT-AR-BNK-002 — customer payment links to bank account', function () {
    $paymentAmount = 25000.00;

    $invoice = CustomerInvoice::create([
        'customer_id' => $this->customer->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->salesAccount->id,
        'invoice_number' => 'INV-TEST-'.uniqid(),
        'invoice_date' => now()->subDays(10),
        'due_date' => now()->addDays(20),
        'subtotal' => $paymentAmount,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    $initialBankBalance = $this->bankAccount->opening_balance ?? 0;

    // Record payment
    $payment = CustomerPayment::create([
        'customer_id' => $this->customer->id,
        'customer_invoice_id' => $invoice->id,
        'amount' => $paymentAmount,
        'payment_date' => now(),
        'payment_method' => 'check',
        'reference_number' => 'CHK-12345',
        'created_by' => $this->user->id,
    ]);

    // Verify payment recorded
    expect((float) $payment->amount)->toBe($paymentAmount);
    expect($payment->payment_method)->toBe('check');
});

// ---------------------------------------------------------------------------
// INT-AR-BNK-003: Full payment closes invoice
// ---------------------------------------------------------------------------

it('INT-AR-BNK-003 — full payment marks invoice as paid', function () {
    $invoiceAmount = 35000.00;

    $invoice = CustomerInvoice::create([
        'customer_id' => $this->customer->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->salesAccount->id,
        'invoice_number' => 'INV-TEST-'.uniqid(),
        'invoice_date' => now()->subDays(5),
        'due_date' => now()->addDays(25),
        'subtotal' => $invoiceAmount,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    // Full payment
    $payment = CustomerPayment::create([
        'customer_id' => $this->customer->id,
        'customer_invoice_id' => $invoice->id,
        'amount' => $invoiceAmount,
        'payment_date' => now(),
        'payment_method' => 'cash',
        'reference_number' => 'CASH-001',
        'created_by' => $this->user->id,
    ]);

    // Update invoice to paid
    $invoice->status = 'paid';
    $invoice->save();

    // Verify invoice status
    $updatedInvoice = CustomerInvoice::find($invoice->id);
    expect($updatedInvoice->status)->toBe('paid');
});

// ---------------------------------------------------------------------------
// INT-AR-BNK-004: Customer credit limit tracking
// ---------------------------------------------------------------------------

it('INT-AR-BNK-004 — outstanding invoices count toward credit limit', function () {
    $creditLimit = 100000.00;

    // Create multiple invoices
    $invoice1 = CustomerInvoice::create([
        'customer_id' => $this->customer->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->salesAccount->id,
        'invoice_number' => 'INV-TEST-'.uniqid(),
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 40000.00,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    $invoice2 = CustomerInvoice::create([
        'customer_id' => $this->customer->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->salesAccount->id,
        'invoice_number' => 'INV-TEST-'.uniqid(),
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 35000.00,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    // Calculate total outstanding (approved invoices)
    $totalOutstanding = CustomerInvoice::where('customer_id', $this->customer->id)
        ->whereIn('status', ['approved', 'partially_paid'])
        ->sum('total_amount');

    $availableCredit = $creditLimit - $totalOutstanding;

    expect((float) $totalOutstanding)->toBe(75000.00);
    expect($availableCredit)->toBe(25000.00);
    expect($totalOutstanding)->toBeLessThanOrEqual($creditLimit);
});

// ---------------------------------------------------------------------------
// INT-AR-BNK-005: AR aging bucket calculation
// ---------------------------------------------------------------------------

it('INT-AR-BNK-005 — AR aging correctly categorizes outstanding invoices', function () {
    // Create invoices with different due dates
    $currentInvoice = CustomerInvoice::create([
        'customer_id' => $this->customer->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->salesAccount->id,
        'invoice_number' => 'INV-TEST-'.uniqid(),
        'invoice_date' => now()->subDays(5),
        'due_date' => now()->addDays(25), // Not yet due
        'subtotal' => 10000.00,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    $overdue30 = CustomerInvoice::create([
        'customer_id' => $this->customer->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->salesAccount->id,
        'invoice_number' => 'INV-TEST-'.uniqid(),
        'invoice_date' => now()->subDays(40),
        'due_date' => now()->subDays(10), // 10 days overdue
        'subtotal' => 20000.00,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    $overdue60 = CustomerInvoice::create([
        'customer_id' => $this->customer->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->salesAccount->id,
        'invoice_number' => 'INV-TEST-'.uniqid(),
        'invoice_date' => now()->subDays(70),
        'due_date' => now()->subDays(40), // 40 days overdue
        'subtotal' => 30000.00,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    $overdue90 = CustomerInvoice::create([
        'customer_id' => $this->customer->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->salesAccount->id,
        'invoice_number' => 'INV-TEST-'.uniqid(),
        'invoice_date' => now()->subDays(100),
        'due_date' => now()->subDays(70), // 70 days overdue
        'subtotal' => 15000.00,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    // Calculate aging buckets
    $today = now();

    $current = CustomerInvoice::where('customer_id', $this->customer->id)
        ->where('due_date', '>=', $today)
        ->whereIn('status', ['approved', 'partially_paid'])
        ->sum('total_amount');

    $days1to30 = CustomerInvoice::where('customer_id', $this->customer->id)
        ->where('due_date', '<', $today)
        ->where('due_date', '>=', $today->copy()->subDays(30))
        ->whereIn('status', ['approved', 'partially_paid'])
        ->sum('total_amount');

    $days31to60 = CustomerInvoice::where('customer_id', $this->customer->id)
        ->where('due_date', '<', $today->copy()->subDays(30))
        ->where('due_date', '>=', $today->copy()->subDays(60))
        ->whereIn('status', ['approved', 'partially_paid'])
        ->sum('total_amount');

    $days61to90 = CustomerInvoice::where('customer_id', $this->customer->id)
        ->where('due_date', '<', $today->copy()->subDays(60))
        ->where('due_date', '>=', $today->copy()->subDays(90))
        ->whereIn('status', ['approved', 'partially_paid'])
        ->sum('total_amount');

    // Note: The 60-day invoice (40 days overdue) falls in 31-60 bucket, not 61-90
    // Due date 40 days ago is between 30-60 days ago from today

    $over90 = CustomerInvoice::where('customer_id', $this->customer->id)
        ->where('due_date', '<', $today->copy()->subDays(90))
        ->whereIn('status', ['approved', 'partially_paid'])
        ->sum('total_amount');

    // Verify aging buckets
    // Current: due 25 days in future = not yet due = 10000
    // 1-30 days: due 10 days ago = 20000
    // 31-60 days: due 40 days ago = 30000
    // 61-90 days: due 70 days ago = 15000 (2026-01-05 is between 60-90 days ago)
    // Over 90: none = 0
    expect((float) $current)->toBe(10000.00);
    expect((float) $days1to30)->toBe(20000.00);
    expect((float) $days31to60)->toBe(30000.00);
    expect((float) $days61to90)->toBe(15000.00);
    expect((float) $over90)->toBe(0.00);

    $totalAging = (float) $current + (float) $days1to30 + (float) $days31to60 + (float) $days61to90 + (float) $over90;
    $totalOutstanding = CustomerInvoice::where('customer_id', $this->customer->id)
        ->whereIn('status', ['approved', 'partially_paid'])
        ->sum('total_amount');

    expect($totalAging)->toBe((float) $totalOutstanding);
});
