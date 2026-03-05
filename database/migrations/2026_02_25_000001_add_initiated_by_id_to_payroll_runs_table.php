<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PR-003 SoD: Add initiated_by_id to payroll_runs.
 *
 * - `initiated_by_id` — the user who created/locked the run (SoD subject).
 *   Backfilled from `created_by` for all existing rows.
 * - DB-level CHECK constraint ensures the initiator cannot also be the approver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->unsignedBigInteger('initiated_by_id')
                ->nullable()
                ->after('created_by')
                ->comment('User who initiated/locked this run — must differ from approved_by (PR-003 SoD)');

            $table->foreign('initiated_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // Backfill: initiator is whoever created the run
        DB::statement('UPDATE payroll_runs SET initiated_by_id = created_by WHERE initiated_by_id IS NULL');

        // DB-level SoD guard: initiator and approver must be different users
        // PostgreSQL CHECK constraint — silently skipped if approved_by is NULL (run not yet approved)
        DB::statement('
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_sod_payroll
            CHECK (
                initiated_by_id IS NULL
                OR approved_by IS NULL
                OR initiated_by_id <> approved_by
            )
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_sod_payroll');

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropForeign(['initiated_by_id']);
            $table->dropColumn('initiated_by_id');
        });
    }
};
