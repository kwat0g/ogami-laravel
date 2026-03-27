<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.4 — Budget approval workflow enhancements.
 *
 * Add reviewed_by/reviewed_at columns and update the status CHECK constraint
 * to include the full lifecycle: draft -> submitted -> reviewed -> approved -> locked
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annual_budgets', function (Blueprint $table): void {
            $table->foreignId('reviewed_by_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_id');
            $table->string('review_remarks')->nullable()->after('reviewed_at');
        });

        // Update status constraint to include full lifecycle
        DB::statement('ALTER TABLE annual_budgets DROP CONSTRAINT IF EXISTS chk_annual_budgets_status');
        DB::statement("ALTER TABLE annual_budgets ADD CONSTRAINT chk_annual_budgets_status CHECK (status IN ('draft','submitted','reviewed','approved','rejected','locked'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE annual_budgets DROP CONSTRAINT IF EXISTS chk_annual_budgets_status');

        Schema::table('annual_budgets', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by_id']);
            $table->dropColumn(['reviewed_by_id', 'reviewed_at', 'review_remarks']);
        });
    }
};
