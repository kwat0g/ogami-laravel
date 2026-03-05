<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 16 — Migration 100038
 *
 * 1. Extend chart_of_accounts with PFRS classification columns needed by
 *    Balance Sheet and Cash Flow report engines.
 * 2. Create bank_accounts, bank_transactions, bank_reconciliations tables
 *    for the Bank Reconciliation workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Extend chart_of_accounts ──────────────────────────────────────
        Schema::table('chart_of_accounts', function (Blueprint $table): void {
            // PFRS current/non-current split (Balance Sheet)
            $table->boolean('is_current')->default(true)->after('is_system');

            // Balance Sheet classification section
            $table->string('bs_classification', 50)->nullable()->after('is_current');

            // Cash Flow classification (Indirect Method)
            $table->string('cf_classification', 30)->nullable()->after('bs_classification');
        });

        // Add CHECK constraints for bs_classification and cf_classification
        DB::statement("ALTER TABLE chart_of_accounts
            ADD CONSTRAINT chk_coa_bs_classification
            CHECK (bs_classification IS NULL OR bs_classification IN (
                'current_asset','non_current_asset',
                'current_liability','non_current_liability',
                'equity','none'
            ))");

        DB::statement("ALTER TABLE chart_of_accounts
            ADD CONSTRAINT chk_coa_cf_classification
            CHECK (cf_classification IS NULL OR cf_classification IN (
                'operating','investing','financing','cash_equivalent','none'
            ))");

        // ── 2. bank_accounts ─────────────────────────────────────────────────
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 200);
            $table->string('account_number', 50)->unique();
            $table->string('bank_name', 200);
            $table->string('account_type', 20);
            // Links to a GL ASSET account representing cash in this bank
            $table->unsignedBigInteger('account_id')->nullable();
            $table->decimal('opening_balance', 15, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->restrictOnDelete();
        });

        DB::statement("ALTER TABLE bank_accounts
            ADD CONSTRAINT chk_bank_account_type
            CHECK (account_type IN ('checking','savings'))");

        // ── 3. bank_reconciliations ──────────────────────────────────────────
        // Created before bank_transactions so FK can reference it.
        Schema::create('bank_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bank_account_id');
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('opening_balance', 15, 4)->default(0);
            $table->decimal('closing_balance', 15, 4)->default(0);
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('certified_by')->nullable();
            $table->timestamp('certified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('bank_account_id')
                ->references('id')
                ->on('bank_accounts')
                ->restrictOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('certified_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        DB::statement("ALTER TABLE bank_reconciliations
            ADD CONSTRAINT chk_bank_recon_status
            CHECK (status IN ('draft','certified'))");

        // SoD layer 4: certifier cannot be the drafter
        DB::statement('ALTER TABLE bank_reconciliations
            ADD CONSTRAINT chk_bank_recon_sod
            CHECK (certified_by IS NULL OR certified_by != created_by)');

        // ── 4. bank_transactions ─────────────────────────────────────────────
        Schema::create('bank_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bank_account_id');
            $table->date('transaction_date');
            $table->text('description');
            $table->decimal('amount', 15, 4);
            $table->string('transaction_type', 10); // debit | credit
            $table->string('reference_number', 100)->nullable();
            $table->string('status', 20)->default('unmatched');
            // Set when matched to a GL journal entry line
            $table->unsignedBigInteger('journal_entry_line_id')->nullable();
            // Set when included in a reconciliation
            $table->unsignedBigInteger('bank_reconciliation_id')->nullable();
            $table->timestamps();

            $table->foreign('bank_account_id')
                ->references('id')
                ->on('bank_accounts')
                ->restrictOnDelete();

            $table->foreign('journal_entry_line_id')
                ->references('id')
                ->on('journal_entry_lines')
                ->nullOnDelete();

            $table->foreign('bank_reconciliation_id')
                ->references('id')
                ->on('bank_reconciliations')
                ->nullOnDelete();
        });

        DB::statement("ALTER TABLE bank_transactions
            ADD CONSTRAINT chk_bank_transaction_type
            CHECK (transaction_type IN ('debit','credit'))");

        DB::statement("ALTER TABLE bank_transactions
            ADD CONSTRAINT chk_bank_transaction_status
            CHECK (status IN ('unmatched','matched','reconciled'))");

        DB::statement('ALTER TABLE bank_transactions
            ADD CONSTRAINT chk_bank_transaction_amount_positive
            CHECK (amount > 0)');

        // Indexes for common query patterns
        DB::statement('CREATE INDEX idx_bank_txn_account_status ON bank_transactions(bank_account_id, status)');
        DB::statement('CREATE INDEX idx_bank_txn_date ON bank_transactions(transaction_date)');
        DB::statement('CREATE INDEX idx_bank_recon_account ON bank_reconciliations(bank_account_id, status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_accounts');

        Schema::table('chart_of_accounts', function (Blueprint $table): void {
            $table->dropColumn(['is_current', 'bs_classification', 'cf_classification']);
        });
    }
};
