<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the pay_periods table for structured pay-period management.
 *
 * This is an additive change — the existing `pay_period_label` VARCHAR on
 * `payroll_runs` is left intact. The `pay_period_id` FK is nullable so that
 * historical runs without a linked pay period are not affected.
 *
 * A unique constraint on (cutoff_start, cutoff_end, frequency) prevents
 * duplicate periods from being created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_periods', function (Blueprint $table) {
            $table->id();

            // Human-readable label (e.g., "Feb 2026 1st", "Mar 2026 13th Month")
            $table->string('label', 60);

            // Cutoff window — inclusive on both ends
            $table->date('cutoff_start');
            $table->date('cutoff_end');

            // Scheduled bank release date
            $table->date('pay_date');

            // Payroll frequency determines SSS/PhilHealth/PagIBIG deduction timing
            $table->enum('frequency', ['semi_monthly', 'monthly', 'weekly'])
                ->default('semi_monthly');

            // open = data entry allowed; closed = finalized, no edits
            $table->enum('status', ['open', 'closed'])
                ->default('open')
                ->index();

            // Soft uniqueness: only one open period per cutoff window per frequency
            $table->unique(['cutoff_start', 'cutoff_end', 'frequency'], 'uq_pay_periods_window');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['cutoff_end', 'status'], 'idx_pay_periods_end_status');
        });

        // Add nullable FK from payroll_runs → pay_periods (additive, no data loss)
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->unsignedBigInteger('pay_period_id')
                ->nullable()
                ->after('pay_period_label')
                ->comment('Optional FK to pay_periods for structured period management');

            $table->foreign('pay_period_id')
                ->references('id')
                ->on('pay_periods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropForeign(['pay_period_id']);
            $table->dropColumn('pay_period_id');
        });

        Schema::dropIfExists('pay_periods');
    }
};
