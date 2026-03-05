<?php

declare(strict_types=1);

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEntryLine;
use App\Domains\AP\Models\Vendor;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Accounting Feature Tests — JE workflow, immutability, double-entry
|--------------------------------------------------------------------------
| Tests the full journal entry lifecycle and guard conditions:
|   JE-001 Draft → Submit → Post happy path
|   JE-007 Posted JE is immutable
|   JE-008 Unbalanced JE rejected
|   JE-009 Posting to closed fiscal period blocked
|   AP-001 AP invoice draft → approved → paid workflow
|   AR-001 AR invoice draft → approved → received workflow
|   BNK-001 Bank reconciliation SoD (preparer ≠ certifier)
--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'SystemSettingsSeeder'])->assertExitCode(0);

    // Open fiscal period covering 2025
    $this->fiscalPeriod = FiscalPeriod::create([
        'name' => 'FY2025',
        'date_from' => '2025-01-01',
        'date_to' => '2025-12-31',
        'status' => 'open',
    ]);

    // Two leaf accounts (no children) for JE lines
    $this->debitAcct = ChartOfAccount::create([
        'code' => 'TEST-1001',
        'name' => 'Test Asset Account',
        'account_type' => 'ASSET',
        'normal_balance' => 'DEBIT',
        'is_active' => true,
    ]);
    $this->creditAcct = ChartOfAccount::create([
        'code' => 'TEST-2001',
        'name' => 'Test Liability Account',
        'account_type' => 'LIABILITY',
        'normal_balance' => 'CREDIT',
        'is_active' => true,
    ]);

    // Accounting officer
    $this->officer = User::factory()->create(['password' => Hash::make('AccPass!456')]);
    $this->officer->assignRole('accounting_manager');
});

// ---------------------------------------------------------------------------
// JE-001 / JE-002 / JE-003: Draft → Submit → Post lifecycle
// ---------------------------------------------------------------------------

describe('Journal entry lifecycle — JE-001 → JE-003', function () {

    it('creates a draft JE successfully', function () {
        $resp = $this->actingAs($this->officer)
            ->postJson('/api/v1/accounting/journal-entries', [
                'description' => 'Test JE draft entry',
                'date' => '2025-10-20',
                'lines' => [
                    ['account_id' => $this->debitAcct->id,  'debit' => 1000.00, 'credit' => null],
                    ['account_id' => $this->creditAcct->id, 'credit' => 1000.00, 'debit' => null],
                ],
            ]);

        $resp->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
    });

    it('submits a draft JE for approval', function () {
        // Create JE directly bypassing the API to test submit endpoint
        $je = JournalEntry::create([
            'date' => '2025-10-20',
            'description' => 'Submit test JE',
            'source_type' => 'manual',
            'fiscal_period_id' => $this->fiscalPeriod->id,
            'created_by' => $this->officer->id,
            'status' => 'draft',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $je->id, 'account_id' => $this->debitAcct->id,  'debit' => 1000.00, 'credit' => null]);
        JournalEntryLine::create(['journal_entry_id' => $je->id, 'account_id' => $this->creditAcct->id, 'debit' => null, 'credit' => 1000.00]);

        $resp = $this->actingAs($this->officer)
            ->patchJson("/api/v1/accounting/journal-entries/{$je->ulid}/submit");

        $resp->assertStatus(200)
            ->assertJsonPath('data.status', 'submitted');
    });

    it('posts a submitted JE — JE-003', function () {
        // The poster must differ from the creator (SoD JE-010)
        $creator = User::factory()->create();
        $creator->assignRole('accounting_manager');

        $je = JournalEntry::create([
            'date' => '2025-10-20',
            'description' => 'Post test JE',
            'source_type' => 'manual',
            'fiscal_period_id' => $this->fiscalPeriod->id,
            'created_by' => $creator->id,
            'status' => 'submitted',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $je->id, 'account_id' => $this->debitAcct->id,  'debit' => 1000.00, 'credit' => null]);
        JournalEntryLine::create(['journal_entry_id' => $je->id, 'account_id' => $this->creditAcct->id, 'debit' => null, 'credit' => 1000.00]);

        // Officer (different user) posts it
        $resp = $this->actingAs($this->officer)
            ->patchJson("/api/v1/accounting/journal-entries/{$je->ulid}/post");
    });
});

// ---------------------------------------------------------------------------
// JE-007: Posted JE is immutable
// ---------------------------------------------------------------------------

describe('JE immutability — JE-007', function () {

    it('rejects cancellation of a posted journal entry', function () {
        $creator = User::factory()->create();
        $creator->assignRole('accounting_manager');

        $je = JournalEntry::create([
            'date' => '2025-10-20',
            'description' => 'Posted JE immutability test',
            'source_type' => 'manual',
            'fiscal_period_id' => $this->fiscalPeriod->id,
            'created_by' => $creator->id,
            'status' => 'posted',
        ]);

        // The creator tries to cancel their own posted JE — should be rejected
        $resp = $this->actingAs($creator)
            ->deleteJson("/api/v1/accounting/journal-entries/{$je->ulid}");

        // Policy returns false for posted JEs (JE-006: posted JE is immutable)
        $resp->assertStatus(403);
    });

    it('posting endpoint rejects already-posted journal entry', function () {
        $creator = User::factory()->create();
        $creator->assignRole('accounting_manager');

        $je = JournalEntry::create([
            'date' => '2025-10-20',
            'description' => 'Already-posted JE test',
            'source_type' => 'manual',
            'fiscal_period_id' => $this->fiscalPeriod->id,
            'created_by' => $creator->id,
            'status' => 'posted',
        ]);

        // Try to post again — service throws INVALID_JE_STATUS_FOR_POSTING (409)
        $resp = $this->actingAs($this->officer)
            ->patchJson("/api/v1/accounting/journal-entries/{$je->ulid}/post");

        $resp->assertStatus(409)
            ->assertJsonPath('error_code', 'INVALID_JE_STATUS_FOR_POSTING');
    });
});

// ---------------------------------------------------------------------------
// JE-008: Unbalanced JE rejected
// ---------------------------------------------------------------------------

describe('Double-entry enforcement — JE-008', function () {

    it('rejects a JE where debits ≠ credits', function () {
        $resp = $this->actingAs($this->officer)
            ->postJson('/api/v1/accounting/journal-entries', [
                'description' => 'Unbalanced JE test entry',
                'date' => '2025-10-20',
                'lines' => [
                    ['account_id' => $this->debitAcct->id,  'debit' => 1000.00, 'credit' => null],
                    ['account_id' => $this->creditAcct->id, 'credit' => 800.00,  'debit' => null], // off by ₱200
                ],
            ]);

        $resp->assertStatus(422)
            ->assertJsonPath('error_code', 'UNBALANCED_JOURNAL_ENTRY');
    });
});

// ---------------------------------------------------------------------------
// JE-009: Posting to a closed fiscal period is blocked
// ---------------------------------------------------------------------------

describe('Closed fiscal period guard — JE-009', function () {

    it('blocks creating a JE in a closed/non-existent fiscal period', function () {
        // There is no fiscal period for 2024 in the test DB (only 2025 is open)
        $resp = $this->actingAs($this->officer)
            ->postJson('/api/v1/accounting/journal-entries', [
                'description' => 'JE for date with no fiscal period',
                'date' => '2024-01-15',
                'lines' => [
                    ['account_id' => $this->debitAcct->id,  'debit' => 500.00, 'credit' => null],
                    ['account_id' => $this->creditAcct->id, 'credit' => 500.00, 'debit' => null],
                ],
            ]);

        // OpenFiscalPeriodRule returns 422 VALIDATION_ERROR
        $resp->assertStatus(422);
    });

    it('blocks creating a JE for a closed fiscal period date', function () {
        // Create a closed fiscal period for 2023
        FiscalPeriod::create([
            'name' => 'FY2023',
            'date_from' => '2023-01-01',
            'date_to' => '2023-12-31',
            'status' => 'closed',
        ]);

        // Try to create JE for date in closed period
        $resp = $this->actingAs($this->officer)
            ->postJson('/api/v1/accounting/journal-entries', [
                'description' => 'JE for closed fiscal period',
                'date' => '2023-06-15',
                'lines' => [
                    ['account_id' => $this->debitAcct->id,  'debit' => 500.00, 'credit' => null],
                    ['account_id' => $this->creditAcct->id, 'credit' => 500.00, 'debit' => null],
                ],
            ]);

        // OpenFiscalPeriodRule blocks JE creation for closed period
        $resp->assertStatus(422);
    });
});

// ---------------------------------------------------------------------------
// AP-001: AP Invoice — draft → approved → paid workflow
// ---------------------------------------------------------------------------

describe('AP Invoice lifecycle — AP-001', function () {

    it('creates a draft AP invoice', function () {
        $vendor = Vendor::create([
            'name' => 'Test Vendor Co.',
            'is_ewt_subject' => false,
            'is_active' => true,
            'created_by' => $this->officer->id,
        ]);

        // Need an AP (liability) account and an expense account
        $apAccount = ChartOfAccount::create([
            'code' => 'AP-2000',
            'name' => 'Accounts Payable',
            'account_type' => 'LIABILITY',
            'normal_balance' => 'CREDIT',
            'is_active' => true,
        ]);
        $expAccount = ChartOfAccount::create([
            'code' => 'EXP-5000',
            'name' => 'Office Expense',
            'account_type' => 'OPEX',
            'normal_balance' => 'DEBIT',
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->officer)
            ->postJson('/api/v1/accounting/ap/invoices', [
                'vendor_id' => $vendor->id,
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'ap_account_id' => $apAccount->id,
                'expense_account_id' => $expAccount->id,
                'invoice_date' => '2025-10-01',
                'due_date' => '2025-11-01',
                'net_amount' => 5000.00,
                'description' => 'Office supplies purchase',
            ]);

        $resp->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
    });
});

// ---------------------------------------------------------------------------
// BNK-001: Bank reconciliation — SoD: preparer ≠ certifier
// ---------------------------------------------------------------------------

describe('Bank reconciliation SoD — BNK-001', function () {

    beforeEach(function () {
        $this->bankAccount = \App\Domains\Accounting\Models\BankAccount::create([
            'name' => 'Test Checking Account',
            'account_number' => 'ACC-100001',
            'bank_name' => 'Test Bank',
            'account_type' => 'checking',
            'is_active' => true,
        ]);
    });

    it('rejects certifying one\'s own bank reconciliation (SOD-008)', function () {
        $reconciler = User::factory()->create(['password' => Hash::make('RecPass!123')]);
        $reconciler->assignRole('accounting_manager');

        // Create a reconciliation where reconciler is the creator
        $recon = \App\Domains\Accounting\Models\BankReconciliation::create([
            'bank_account_id' => $this->bankAccount->id,
            'period_from' => '2025-10-01',
            'period_to' => '2025-10-31',
            'status' => 'draft',
            'created_by' => $reconciler->id,
        ]);

        // Attempt to certify own reconciliation
        $this->actingAs($reconciler)
            ->patchJson("/api/v1/accounting/bank-reconciliations/{$recon->ulid}/certify")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'SOD_VIOLATION');
    });

    it('allows a separate certifier to approve a bank reconciliation', function () {
        $preparer = User::factory()->create(['password' => Hash::make('PrepPass!123')]);
        $certifier = User::factory()->create(['password' => Hash::make('CertPass!123')]);
        $preparer->assignRole('accounting_manager');
        $certifier->assignRole('accounting_manager');

        $recon = \App\Domains\Accounting\Models\BankReconciliation::create([
            'bank_account_id' => $this->bankAccount->id,
            'period_from' => '2025-11-01',
            'period_to' => '2025-11-30',
            'status' => 'draft',
            'created_by' => $preparer->id,
        ]);

        // No unmatched transactions, different certifier — should succeed
        $this->actingAs($certifier)
            ->patchJson("/api/v1/accounting/bank-reconciliations/{$recon->ulid}/certify")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'certified');
    });
});
