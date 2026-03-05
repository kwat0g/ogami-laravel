<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fiscal Periods — accounting calendar management.
 *
 * Each period covers a contiguous date range with no overlaps allowed.
 * GIST index enforces non-overlapping constraint at DB level (requires btree_gist).
 *
 * Life cycle: open ↔ closed (reversible only by Accounting Manager).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Required for the daterange GIST exclusion index below.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);                    // e.g. "Feb 2026"
            $table->date('date_from');
            $table->date('date_to');
            $table->string('status', 10)->default('open'); // open | closed
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'date_from']);
        });

        DB::statement("ALTER TABLE fiscal_periods
            ADD CONSTRAINT chk_fp_status
            CHECK (status IN ('open','closed'))");

        DB::statement('ALTER TABLE fiscal_periods
            ADD CONSTRAINT chk_fp_date_order
            CHECK (date_to >= date_from)');

        // No overlapping fiscal periods — uses EXCLUSION constraint (GIST doesn't support UNIQUE indexes)
        DB::statement("ALTER TABLE fiscal_periods
            ADD CONSTRAINT no_overlapping_fiscal_periods
            EXCLUDE USING GIST (daterange(date_from, date_to, '[]') WITH &&)");
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_periods');
    }
};
