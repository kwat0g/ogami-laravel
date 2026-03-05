<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expanded Withholding Tax rates per ATC code — effective-date versioned.
 *
 * EWT-001: Rate effective on invoice_date is always used.
 * EWT-002: Form 2307 is generated per vendor per quarter from vendor_payments.
 *
 * ATC codes are BIR-assigned, e.g.:
 *   WC010 — Professional fees, 10%
 *   WC158 — Payments to VAT-registered professionals, 10%
 *   WI010 — Rentals, 5%
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ewt_rates', function (Blueprint $table): void {
            $table->id();
            $table->date('effective_date');
            $table->string('atc_code', 10)->comment('BIR ATC code, e.g. WC010, WI010');
            $table->string('description', 200)->comment('Human-readable description of the ATC');
            $table->decimal('ewt_rate', 8, 6)->comment('Rate as a decimal, e.g. 0.10 for 10%');
            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE ewt_rates
            ADD CONSTRAINT chk_ewt_rate_valid CHECK (ewt_rate > 0 AND ewt_rate <= 1)
        ');

        DB::statement('
            CREATE UNIQUE INDEX idx_ewt_rates_atc_date
            ON ewt_rates (atc_code, effective_date DESC)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('ewt_rates');
    }
};
