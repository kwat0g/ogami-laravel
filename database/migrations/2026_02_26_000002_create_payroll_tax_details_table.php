<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2.3 — Separate tax computation details from payroll_details.
 *
 * Stores TRAIN Law withholding tax computation fields (taxable income, tax
 * bracket used, exemptions applied, YTD accumulators) per payroll detail.
 * Additive — payroll_details columns are preserved for backwards compatibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_tax_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payroll_detail_id')
                ->constrained('payroll_details')
                ->cascadeOnDelete();

            $table->foreignId('payroll_run_id')
                ->constrained('payroll_runs')
                ->cascadeOnDelete();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            // ── TRAIN Law derived status ─────────────────────────────────────
            /** BIR tax status code: S, S1, ME, ME1 … ME4, HF, HF1–HF4 */
            $table->string('bir_status', 10)->nullable()->comment('Derived BIR status code');
            $table->unsignedTinyInteger('qualified_dependents')->default(0);

            // ── Period taxable income breakdown ──────────────────────────────
            $table->unsignedBigInteger('gross_taxable_centavos')->default(0)->comment('Gross pay before non-taxable exclusions');
            $table->unsignedBigInteger('non_taxable_exclusions_centavos')->default(0)->comment('13th month, de minimis, etc.');
            $table->unsignedBigInteger('net_taxable_income_centavos')->default(0)->comment('Amount fed into TRAIN bracket');

            // ── Tax bracket applied ──────────────────────────────────────────
            /** Min of the bracket hit this period, in centavos/year */
            $table->unsignedBigInteger('bracket_floor_centavos')->default(0);
            /** Fixed tax on bracket floor, in centavos */
            $table->unsignedBigInteger('bracket_fixed_tax_centavos')->default(0);
            /** Marginal rate (0–35) as integer basis points, e.g. 20 = 20 % */
            $table->unsignedSmallInteger('bracket_rate_bps')->default(0);
            $table->unsignedBigInteger('withholding_tax_centavos')->default(0)->comment('Computed withholding tax this period');

            // ── Year-to-date accumulators ────────────────────────────────────
            $table->unsignedBigInteger('ytd_taxable_income_centavos')->default(0)->comment('YTD taxable income including this period');
            $table->unsignedBigInteger('ytd_tax_withheld_centavos')->default(0)->comment('YTD tax withheld including this period');

            // ── Annualisation flags ──────────────────────────────────────────
            $table->boolean('is_annualised')->default(false)->comment('December year-end annualisation applied');
            $table->bigInteger('annualisation_adjustment_centavos')->default(0)->comment('+ means extra tax collected; - means refund');

            $table->timestamps();

            $table->unique('payroll_detail_id', 'uq_ptd_payroll_detail');
            $table->index(['payroll_run_id', 'employee_id'], 'idx_ptd_run_employee');
            $table->index('employee_id', 'idx_ptd_employee_ytd');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_tax_details');
    }
};
