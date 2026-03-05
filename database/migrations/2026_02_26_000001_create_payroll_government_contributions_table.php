<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2.2 — Separate government contribution details from payroll_details.
 *
 * Stores SSS / PhilHealth / Pag-IBIG employee AND employer shares per payroll
 * detail record. The source columns in payroll_details are kept to avoid a
 * breaking migration; this table is additive for normalised reporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_government_contributions', function (Blueprint $table) {
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

            // ── SSS ─────────────────────────────────────────────────────────
            $table->unsignedBigInteger('sss_ee_centavos')->default(0)->comment('Employee SSS share (centavos)');
            $table->unsignedBigInteger('sss_er_centavos')->default(0)->comment('Employer SSS share (centavos)');
            $table->unsignedBigInteger('sss_ec_centavos')->default(0)->comment('Employees\' Compensation fund (centavos)');

            // ── PhilHealth ───────────────────────────────────────────────────
            $table->unsignedBigInteger('philhealth_ee_centavos')->default(0)->comment('Employee PhilHealth share (centavos)');
            $table->unsignedBigInteger('philhealth_er_centavos')->default(0)->comment('Employer PhilHealth share (centavos)');

            // ── Pag-IBIG ─────────────────────────────────────────────────────
            $table->unsignedBigInteger('pagibig_ee_centavos')->default(0)->comment('Employee Pag-IBIG share (centavos)');
            $table->unsignedBigInteger('pagibig_er_centavos')->default(0)->comment('Employer Pag-IBIG share (centavos)');

            // ── Metadata ─────────────────────────────────────────────────────
            /** Reference period used for table look-ups (YYYY-MM-01) */
            $table->date('contribution_period')->nullable()->comment('Month used for table lookup');

            $table->timestamps();

            $table->unique('payroll_detail_id', 'uq_pgc_payroll_detail');
            $table->index(['payroll_run_id', 'employee_id'], 'idx_pgc_run_employee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_government_contributions');
    }
};
