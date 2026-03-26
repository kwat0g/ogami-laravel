<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Vendor Invoices ──────────────────────────────────────────────────
        // AP-001: due_date >= invoice_date enforced by DB constraint.
        // AP-005: net_payable = invoice_amount + vat_amount - ewt_amount (computed accessor, not stored).
        // AP-006: only DRAFT is editable (enforced by service/policy).
        Schema::create('vendor_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->nullable()->comment('External vendor invoice number');
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('fiscal_period_id')->constrained('fiscal_periods');
            $table->foreignId('ap_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()
                ->comment('GL: Accounts Payable account code');
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()
                ->comment('GL: Expense account to debit on approval');

            // Dates
            $table->date('invoice_date');
            $table->date('due_date')->comment('AP-001: must be >= invoice_date');

            // Amounts (pesos, NUMERIC 15,4)
            $table->decimal('net_amount', 15, 4)->comment('Pre-VAT amount');
            $table->decimal('vat_amount', 15, 4)->default(0)->comment('AP-003: must match net_amount × vat_rate ±₱0.01');
            $table->decimal('ewt_amount', 15, 4)->default(0)->comment('AP-004: auto-computed when vendor.is_ewt_subject');
            // net_payable is computed: net_amount + vat_amount - ewt_amount

            // VAT (VAT-001: OR number required when VAT applies)
            $table->string('or_number')->nullable()->comment('Official Receipt number; required when vat_amount > 0');
            $table->string('vat_exemption_reason')->nullable()->comment('VAT-003: required when VAT-exempt');

            // EWT
            $table->string('atc_code', 20)->nullable()->comment('ATC code used for this invoice');
            $table->decimal('ewt_rate', 5, 4)->nullable()->comment('Snapshot of rate at invoice_date per EWT-001');

            // Status machine (AP-006)
            $table->string('status', 20)->default('draft')
                ->comment('draft | pending_approval | approved | partially_paid | paid | deleted');
            $table->string('rejection_note')->nullable();

            $table->text('description')->nullable();

            // GL auto-post tracking
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()
                ->comment('GL JE created on approval');

            // Audit
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'status']);
            $table->index(['fiscal_period_id', 'status']);
            $table->index('due_date');
            $table->index('status');

            // AP-001: due_date >= invoice_date
            $table->rawIndex(
                '(CASE WHEN due_date >= invoice_date THEN 1 END)',
                'vendor_invoices_due_after_invoice_check'
            );
        });

        // DB-level AP-001 constraint via CHECK
        DB::statement('ALTER TABLE vendor_invoices ADD CONSTRAINT chk_ap001_due_date CHECK (due_date >= invoice_date)');

        // ── Vendor Payments ──────────────────────────────────────────────────
        // AP-007: partial payments tracked here; AP-008: sum(amount) must not exceed net_payable.
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->date('payment_date');
            $table->decimal('amount', 15, 4)->comment('AP-007: partial payment amount');
            $table->string('reference_number')->nullable()->comment('Check number / bank reference');
            $table->string('payment_method', 30)->default('bank_transfer')
                ->comment('bank_transfer | check | cash');
            $table->text('notes')->nullable();

            // Form 2307 tracking (AP-009)
            $table->boolean('form_2307_generated')->default(false);
            $table->timestamp('form_2307_generated_at')->nullable();

            // GL auto-post of payment
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            // Audit
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index(['vendor_invoice_id']);
            $table->index(['vendor_id', 'payment_date']);
            $table->index('form_2307_generated');
        });

        // AP-008: enforce no overpayment at DB level (sum check done in service layer;
        // this positive amount check prevents zero/negative payments)
        DB::statement('ALTER TABLE vendor_payments ADD CONSTRAINT chk_ap_payment_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('vendor_invoices');
    }
};
