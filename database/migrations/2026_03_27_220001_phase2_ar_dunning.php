<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.2 — AR Dunning / Collection management.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dunning_levels', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('level')->unique();
            $table->unsignedSmallInteger('days_overdue');
            $table->string('name', 100);
            $table->text('template_text');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('dunning_notices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('customer_invoice_id')->constrained('customer_invoices');
            $table->foreignId('dunning_level_id')->constrained('dunning_levels');
            $table->unsignedBigInteger('amount_due_centavos');
            $table->unsignedSmallInteger('days_overdue');
            $table->string('status', 30)->default('generated');
            $table->timestamp('sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE dunning_notices ADD CONSTRAINT chk_dunning_notices_status CHECK (status IN ('generated','sent','acknowledged','escalated','resolved'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('dunning_notices');
        Schema::dropIfExists('dunning_levels');
    }
};
