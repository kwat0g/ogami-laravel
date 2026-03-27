<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.1 — AP Payment Batch processing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_batches', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('batch_number', 50)->unique();
            $table->string('status', 30)->default('draft');
            $table->date('payment_date');
            $table->string('payment_method', 30)->default('bank_transfer');
            $table->unsignedBigInteger('total_amount_centavos')->default(0);
            $table->unsignedInteger('payment_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE payment_batches ADD CONSTRAINT chk_payment_batches_status CHECK (status IN ('draft','submitted','approved','processing','completed','cancelled'))");
        DB::statement("ALTER TABLE payment_batches ADD CONSTRAINT chk_payment_batches_method CHECK (payment_method IN ('bank_transfer','check','cash'))");

        Schema::create('payment_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_batch_id')->constrained('payment_batches')->cascadeOnDelete();
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->unsignedBigInteger('amount_centavos');
            $table->string('status', 30)->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE payment_batch_items ADD CONSTRAINT chk_payment_batch_items_status CHECK (status IN ('pending','paid','failed','skipped'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_batch_items');
        Schema::dropIfExists('payment_batches');
    }
};
