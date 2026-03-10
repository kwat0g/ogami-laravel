<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer credit/debit notes — adjustments against customer invoices.
 *
 * credit_note — we issue credit to customer reducing what they owe (e.g. returned goods, price adjustments)
 * debit_note  — we issue a debit to customer for additional charges (e.g. shipping surcharges)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('cn_reference', 40)->unique();   // CN-AR-YYYY-MM-NNNNN
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('customer_invoice_id')->nullable()->constrained('customer_invoices')->nullOnDelete();
            $table->string('note_type', 10)->default('credit');  // credit|debit
            $table->date('note_date');
            $table->unsignedBigInteger('amount_centavos');
            $table->string('reason', 500);
            $table->string('status', 20)->default('draft');      // draft|posted
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('ar_account_id')->constrained('chart_of_accounts');
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE customer_credit_notes ADD CONSTRAINT chk_customer_credit_notes_type
            CHECK (note_type IN ('credit','debit'))");
        DB::statement("ALTER TABLE customer_credit_notes ADD CONSTRAINT chk_customer_credit_notes_status
            CHECK (status IN ('draft','posted'))");
        DB::statement("ALTER TABLE customer_credit_notes ADD CONSTRAINT chk_customer_credit_notes_amount
            CHECK (amount_centavos > 0)");

        DB::statement('CREATE SEQUENCE IF NOT EXISTS customer_credit_note_seq START 1');
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_notes');
        DB::statement('DROP SEQUENCE IF EXISTS customer_credit_note_seq');
    }
};
