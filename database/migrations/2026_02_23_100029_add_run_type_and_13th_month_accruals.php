<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 11: 13th Month Pay Foundation.
 *
 * Two changes:
 *  1. Add `run_type` column to payroll_runs (regular | thirteenth_month).
 *     13TH-005: 13th month runs are separate from regular runs.
 *     13TH-006: They use the same state machine and approval workflow.
 *
 *  2. Create `thirteenth_month_accruals` table.
 *     13TH-001: Each month when a regular payroll run is posted, the system
 *     records the employee's basic salary earned that month as an accrual.
 *     13TH-002: 13th month pay = sum(accrual_amount) / 12 (divisor always 12).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add run_type to payroll_runs ───────────────────────────────────
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->string('run_type', 30)
                ->default('regular')
                ->after('status')
                ->comment('regular | thirteenth_month');
        });

        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_pr_run_type
            CHECK (run_type IN ('regular', 'thirteenth_month'))
        ");

        // Re-scope the overlap exclusion constraint to be per run_type.
        // Regular runs cannot overlap each other; 13th month runs cannot
        // overlap each other; but a regular run CAN share dates with a
        // thirteenth_month run (yearly cutoff covers the same period).
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS excl_payroll_run_dates');

        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT excl_payroll_run_dates_per_type
            EXCLUDE USING gist (
                run_type WITH =,
                daterange(cutoff_start, cutoff_end, '[]') WITH &&
            ) WHERE (status NOT IN ('cancelled'))
        ");

        // ── 2. Create thirteenth_month_accruals ───────────────────────────────
        Schema::create('thirteenth_month_accruals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('year')
                ->comment('Calendar year, e.g. 2026');
            $table->unsignedTinyInteger('month')
                ->comment('1–12 calendar month');
            $table->unsignedBigInteger('basic_salary_earned_centavos')
                ->comment('Basic pay earned in this month — prorated if hired/resigned mid-month');
            $table->unsignedBigInteger('accrual_amount_centavos')
                ->comment('Equals basic_salary_earned_centavos (alias for clarity in formula)');
            $table->foreignId('payroll_run_id')
                ->nullable()
                ->constrained('payroll_runs')
                ->nullOnDelete()
                ->comment('The regular payroll run that generated this accrual');
            $table->timestamps();

            // One accrual per employee per month per year
            $table->unique(['employee_id', 'year', 'month'], 'uq_13th_month_accrual');
            $table->index(['employee_id', 'year']);
        });

        // Enforce valid month range at DB level
        DB::statement('
            ALTER TABLE thirteenth_month_accruals
            ADD CONSTRAINT chk_13th_month_range CHECK (month BETWEEN 1 AND 12)
        ');

        DB::statement('
            ALTER TABLE thirteenth_month_accruals
            ADD CONSTRAINT chk_13th_year_range CHECK (year BETWEEN 2020 AND 2099)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('thirteenth_month_accruals');

        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS excl_payroll_run_dates_per_type');
        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT excl_payroll_run_dates
            EXCLUDE USING gist (daterange(cutoff_start, cutoff_end, '[]') WITH &&)
            WHERE (status NOT IN ('cancelled'))
        ");

        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropConstraint('chk_pr_run_type');
            $table->dropColumn('run_type');
        });
    }
};
