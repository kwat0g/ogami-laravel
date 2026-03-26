<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BUD-002: Add budget approval workflow to annual_budgets.
 *
 * Workflow: draft → submitted → approved|rejected
 * VP is the final approver; SOD enforced (submitter ≠ approver).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annual_budgets', function (Blueprint $table): void {
            $table->string('status', 20)->default('draft')->after('notes')
                ->comment('draft|submitted|approved|rejected');

            // Submission
            $table->foreignId('submitted_by_id')->nullable()->after('status')
                ->constrained('users');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by_id');

            // VP Approval
            $table->foreignId('approved_by_id')->nullable()->after('submitted_at')
                ->constrained('users');
            $table->timestamp('approved_at')->nullable()->after('approved_by_id');
            $table->text('approval_remarks')->nullable()->after('approved_at');
        });

        DB::statement("
            ALTER TABLE annual_budgets ADD CONSTRAINT chk_budget_status
            CHECK (status IN ('draft','submitted','approved','rejected'))
        ");

        // SOD: submitter cannot also be the approver
        DB::statement('
            ALTER TABLE annual_budgets ADD CONSTRAINT chk_sod_budget_approval
            CHECK (approved_by_id IS NULL OR approved_by_id <> submitted_by_id)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE annual_budgets DROP CONSTRAINT IF EXISTS chk_sod_budget_approval');
        DB::statement('ALTER TABLE annual_budgets DROP CONSTRAINT IF EXISTS chk_budget_status');

        Schema::table('annual_budgets', function (Blueprint $table): void {
            $table->dropForeign(['submitted_by_id']);
            $table->dropForeign(['approved_by_id']);
            $table->dropColumn([
                'status', 'submitted_by_id', 'submitted_at',
                'approved_by_id', 'approved_at', 'approval_remarks',
            ]);
        });
    }
};
