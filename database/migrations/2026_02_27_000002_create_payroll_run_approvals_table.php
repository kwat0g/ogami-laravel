<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates payroll_run_approvals table.
 *
 * Replaces the single approved_by/approved_at columns on payroll_runs with a
 * proper per-stage approval record that stores:
 *  - which stage (HR_REVIEW or ACCOUNTING)
 *  - what action (APPROVED, RETURNED, REJECTED)
 *  - who performed it
 *  - the 3-checkbox acknowledgement payload
 *  - an optional comments field
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_run_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')
                ->constrained('payroll_runs')
                ->cascadeOnDelete();

            // HR_REVIEW | ACCOUNTING
            $table->string('stage', 20);

            // APPROVED | RETURNED | REJECTED
            $table->string('action', 20);

            $table->foreignId('actor_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('comments')->nullable();

            // JSON array of acknowledged checkbox keys
            $table->jsonb('checkboxes_checked')->nullable();

            $table->timestamp('acted_at')->useCurrent();

            // A run can only have one approval record per stage
            // (the last action wins; old rows are deleted before inserting a new one)
            $table->timestamps();

            $table->index(['payroll_run_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_approvals');
    }
};
