<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 15 – AR Module
 *
 * Creates:
 *   customers                   — master record (AR-001–006)
 *   customer_invoices            — invoice lifecycle (AR-003: INV-YYYY-MM-NNNNNN)
 *   customer_payments            — receipts applied to invoices
 *   customer_advance_payments    — overpayment tracking (AR-005)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Customers ─────────────────────────────────────────────────────────
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('tin', 20)->nullable()->unique();
            $table->string('email', 200)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('contact_person', 200)->nullable();
            $table->text('address')->nullable();
            $table->string('billing_address', 500)->nullable();
            // AR-001: credit limit enforcement
            $table->decimal('credit_limit', 15, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('ar_account_id')->nullable()->comment('Default AR GL account for this customer');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('ar_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        // ── Customer Invoices ─────────────────────────────────────────────────
        Schema::create('customer_invoices', function (Blueprint $table) {
            $table->id();
            // AR-003: system-generated invoice number
            $table->string('invoice_number', 30)->nullable()->unique()->comment('INV-YYYY-MM-NNNNNN, set on approval');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('fiscal_period_id');
            $table->unsignedBigInteger('ar_account_id')->comment('Debit side: Accounts Receivable');
            $table->unsignedBigInteger('revenue_account_id')->comment('Credit side: Revenue');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2);
            // VAT-002: output VAT computed as subtotal × vat_rate from system_settings
            $table->decimal('vat_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->storedAs('subtotal + vat_amount');
            $table->string('vat_exemption_reason', 200)->nullable();
            $table->string('description', 500)->nullable();
            // AR-006: bad debt tracking
            $table->string('status', 30)->default('draft')
                ->comment('draft|approved|partially_paid|paid|written_off|cancelled');
            $table->string('write_off_reason', 500)->nullable();
            $table->unsignedBigInteger('write_off_approved_by')->nullable();
            $table->timestamp('write_off_at')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('write_off_journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('fiscal_period_id')->references('id')->on('fiscal_periods');
            $table->foreign('ar_account_id')->references('id')->on('chart_of_accounts');
            $table->foreign('revenue_account_id')->references('id')->on('chart_of_accounts');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('write_off_journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('write_off_approved_by')->references('id')->on('users')->nullOnDelete();

            // Check constraints added via DB::statement() after table creation
        });

        // ── Customer Payments ─────────────────────────────────────────────────
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_invoice_id');
            $table->unsignedBigInteger('customer_id');
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('reference_number', 100)->nullable();
            $table->string('payment_method', 30)->nullable()
                ->comment('bank_transfer|check|cash|online');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('customer_invoice_id')->references('id')->on('customer_invoices');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');

            // Check constraint added via DB::statement() after table creation
        });

        // ── Customer Advance Payments (AR-005 overpayment) ────────────────────
        Schema::create('customer_advance_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->date('received_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('applied_amount', 15, 2)->default(0.00);
            $table->string('reference_number', 100)->nullable();
            $table->string('status', 20)->default('available')
                ->comment('available|partially_applied|fully_applied');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');

            // Check constraints added via DB::statement() after table creation
        });

        DB::statement('ALTER TABLE customer_invoices ADD CONSTRAINT chk_ar_due_date CHECK (due_date >= invoice_date)');
        DB::statement('ALTER TABLE customer_invoices ADD CONSTRAINT chk_ar_invoice_subtotal_positive CHECK (subtotal > 0)');
        DB::statement('ALTER TABLE customer_payments ADD CONSTRAINT chk_ar_payment_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE customer_advance_payments ADD CONSTRAINT chk_ar_advance_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE customer_advance_payments ADD CONSTRAINT chk_ar_advance_applied CHECK (applied_amount >= 0 AND applied_amount <= amount)');
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_advance_payments');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('customer_invoices');
        Schema::dropIfExists('customers');
    }
};
