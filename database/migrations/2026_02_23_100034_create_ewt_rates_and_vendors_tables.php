<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── EWT Rates (ATC code lookup) ──────────────────────────────────────
        // Table already created by 2026_02_23_100009_create_ewt_rates_table.php.
        // Add indexes and seed data that may not exist yet.
        if (! Schema::hasTable('ewt_rates')) {
            Schema::create('ewt_rates', function (Blueprint $table) {
                $table->id();
                $table->string('atc_code', 20)->comment('BIR ATC code, e.g. WC010, WC158');
                $table->string('description');
                $table->decimal('rate', 5, 4)->comment('e.g. 0.0100 = 1%');
                $table->date('effective_from');
                $table->date('effective_to')->nullable()->comment('NULL = currently active');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['atc_code', 'effective_from']);
                $table->index('is_active');
            });
        }

        // Seed data handled by EwtRateSeeder; skip inline seeding here to avoid schema mismatch.

        // ── Vendors ──────────────────────────────────────────────────────────
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tin', 20)->nullable()->comment('AP-011: required before any payment');
            $table->foreignId('ewt_rate_id')->nullable()->constrained('ewt_rates')->nullOnDelete();
            $table->string('atc_code', 20)->nullable();
            $table->boolean('is_ewt_subject')->default(false)->comment('AP-004: EWT auto-computed when true');
            $table->boolean('is_active')->default(true)->comment('AP-002: invoice blocked when false');
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('is_ewt_subject');
            $table->unique('tin', 'vendors_tin_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('ewt_rates');
    }
};
