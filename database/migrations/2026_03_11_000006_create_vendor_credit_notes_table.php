<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor credit/debit notes — adjustments against vendor invoices.
 *
 * credit_note — vendor issues credit reducing what we owe (e.g. returned goods)
 * debit_note  — we issue a debit to vendor for penalties / short delivery charges
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('cn_reference', 40)->unique();   // CN-AP-YYYY-MM-NNNNN
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->foreignId('vendor_invoice_id')->nullable()->constrained('vendor_invoices')->nullOnDelete();
            $table->string('note_type', 10)->default('credit');  // credit|debit
            $table->date('note_date');
            $table->unsignedBigInteger('amount_centavos');        // absolute value
            $table->string('reason', 500);
            $table->string('status', 20)->default('draft');       // draft|posted
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('ap_account_id')->constrained('chart_of_accounts');
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE vendor_credit_notes ADD CONSTRAINT chk_vendor_credit_notes_type
            CHECK (note_type IN ('credit','debit'))");
        DB::statement("ALTER TABLE vendor_credit_notes ADD CONSTRAINT chk_vendor_credit_notes_status
            CHECK (status IN ('draft','posted'))");
        DB::statement('ALTER TABLE vendor_credit_notes ADD CONSTRAINT chk_vendor_credit_notes_amount
            CHECK (amount_centavos > 0)');

        DB::statement('CREATE SEQUENCE IF NOT EXISTS vendor_credit_note_seq START 1');
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_credit_notes');
        DB::statement('DROP SEQUENCE IF EXISTS vendor_credit_note_seq');
    }
};
