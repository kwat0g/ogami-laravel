<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll Details — one row per employee per payroll run.
 *
 * All monetary columns are unsigned bigint centavos (₱ × 100).
 * Computed by the 17-step PayrollComputationService pipeline.
 *
 * Snapshot columns (salary_rate_centavos, etc.) capture the values
 * at computation time so historical payslips remain accurate even
 * after salary changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')
                ->constrained('payroll_runs')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();

            // ── Snapshots (capture at computation time) ──────────────────────
            $table->unsignedBigInteger('basic_monthly_rate_centavos');
            $table->unsignedBigInteger('daily_rate_centavos');
            $table->unsignedBigInteger('hourly_rate_centavos');
            $table->unsignedSmallInteger('working_days_in_period')->default(13); // ~26/2
            $table->string('pay_basis', 10)->default('monthly'); // monthly|daily|hourly

            // ── Attendance Summary ────────────────────────────────────────────
            $table->unsignedSmallInteger('days_worked')->default(0);
            $table->unsignedSmallInteger('days_absent')->default(0);
            $table->unsignedSmallInteger('days_late_minutes')->default(0);        // total tardiness in minutes
            $table->unsignedSmallInteger('undertime_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_regular_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_rest_day_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_holiday_minutes')->default(0);
            $table->unsignedSmallInteger('night_diff_minutes')->default(0);
            $table->unsignedSmallInteger('regular_holiday_days')->default(0);
            $table->unsignedSmallInteger('special_holiday_days')->default(0);
            $table->unsignedSmallInteger('leave_days_paid')->default(0);
            $table->unsignedSmallInteger('leave_days_unpaid')->default(0);

            // ── Earnings ─────────────────────────────────────────────────────
            $table->unsignedBigInteger('basic_pay_centavos')->default(0);
            $table->unsignedBigInteger('overtime_pay_centavos')->default(0);
            $table->unsignedBigInteger('holiday_pay_centavos')->default(0);
            $table->unsignedBigInteger('night_diff_pay_centavos')->default(0);
            $table->unsignedBigInteger('gross_pay_centavos')->default(0);

            // ── Government Deductions ─────────────────────────────────────────
            $table->unsignedBigInteger('sss_ee_centavos')->default(0);          // Employee SSS share
            $table->unsignedBigInteger('philhealth_ee_centavos')->default(0);   // Employee PhilHealth share
            $table->unsignedBigInteger('pagibig_ee_centavos')->default(0);      // Employee Pag-IBIG share
            $table->unsignedBigInteger('withholding_tax_centavos')->default(0); // TRAIN withheld this period

            // ── Loan Deductions ───────────────────────────────────────────────
            $table->unsignedBigInteger('loan_deductions_centavos')->default(0); // total across all loans
            $table->json('loan_deduction_detail')->nullable();                   // [{loan_id, amount_centavos}]

            // ── Other Deductions & Adjustments ───────────────────────────────
            $table->unsignedBigInteger('other_deductions_centavos')->default(0);
            $table->unsignedBigInteger('total_deductions_centavos')->default(0);

            // ── Net Pay ───────────────────────────────────────────────────────
            $table->unsignedBigInteger('net_pay_centavos')->default(0);
            $table->boolean('is_below_min_wage')->default(false); // EDGE-001 flag
            $table->boolean('has_deferred_deductions')->default(false); // LN-007 deferral

            // ── YTD accumulators (for cumulative tax method TAX-001) ──────────
            $table->unsignedBigInteger('ytd_taxable_income_centavos')->default(0);
            $table->unsignedBigInteger('ytd_tax_withheld_centavos')->default(0);

            // ── Status ────────────────────────────────────────────────────────
            $table->string('status', 20)->default('computed'); // computed|approved|reversed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id'], 'uq_payroll_detail_run_emp');
            $table->index('employee_id');
        });

        DB::statement("ALTER TABLE payroll_details ADD CONSTRAINT chk_pd_status
            CHECK (status IN ('computed','approved','reversed'))");

        DB::statement("ALTER TABLE payroll_details ADD CONSTRAINT chk_pd_pay_basis
            CHECK (pay_basis IN ('monthly','daily','hourly'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
    }
};
