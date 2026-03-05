<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll Runs — one record per pay period execution.
 *
 * Semi-monthly schedule: cutoff_start → cutoff_end, pay_date.
 * State machine: draft → locked → processing → completed → cancelled.
 *
 * PR-001: Only one run per pay period (EXCLUDE constraint on overlapping dates).
 * PR-002: Locking freezes attendance, leave, and loan data for the period.
 * PR-003: SoD — run creator ≠ approver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->unique();   // PR-YYYY-NNNNNN
            $table->string('pay_period_label', 50);         // e.g. "Feb 2026 1st"
            $table->date('cutoff_start');
            $table->date('cutoff_end');
            $table->date('pay_date');
            $table->string('status', 20)->default('draft');
            // status: draft|locked|processing|completed|cancelled
            $table->unsignedInteger('total_employees')->default(0);
            $table->unsignedBigInteger('gross_pay_total_centavos')->default(0);
            $table->unsignedBigInteger('total_deductions_centavos')->default(0);
            $table->unsignedBigInteger('net_pay_total_centavos')->default(0);
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'pay_date']);
            $table->index('cutoff_start');
        });

        DB::statement("ALTER TABLE payroll_runs ADD CONSTRAINT chk_pr_status
            CHECK (status IN ('draft','locked','processing','completed','cancelled'))");

        DB::statement('ALTER TABLE payroll_runs ADD CONSTRAINT chk_pr_cutoff_order
            CHECK (cutoff_end >= cutoff_start)');

        DB::statement('ALTER TABLE payroll_runs ADD CONSTRAINT chk_pr_pay_date_order
            CHECK (pay_date >= cutoff_end)');

        // PR-001: No overlapping pay periods (exclusive constraint)
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement("ALTER TABLE payroll_runs
            ADD CONSTRAINT excl_payroll_run_dates
            EXCLUDE USING gist (daterange(cutoff_start, cutoff_end, '[]') WITH &&)
            WHERE (status NOT IN ('cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
