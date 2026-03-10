<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add Accounting budget-check step to the PR approval chain.
 *
 * New workflow: ... → reviewed (Officer) → budget_checked (Accounting) → approved (VP)
 *                                                         ↓
 *                                                      returned (needs revision → resubmit from draft)
 *
 * Also adds `return_reason`, `returned_by_id`, `returned_at` for the returned status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            // Budget-check stage (Accounting Officer)
            $table->foreignId('budget_checked_by_id')
                ->nullable()
                ->after('reviewed_comments')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('budget_checked_at')->nullable()->after('budget_checked_by_id');
            $table->text('budget_checked_comments')->nullable()->after('budget_checked_at');

            // Returned stage (Accounting returns to requester for revision)
            $table->foreignId('returned_by_id')
                ->nullable()
                ->after('budget_checked_comments')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('returned_at')->nullable()->after('returned_by_id');
            $table->text('return_reason')->nullable()->after('returned_at');
        });

        // Update the status CHECK constraint to include the new statuses
        DB::statement('ALTER TABLE purchase_requests DROP CONSTRAINT chk_pr_status');
        DB::statement("
            ALTER TABLE purchase_requests
            ADD CONSTRAINT chk_pr_status
                CHECK (status IN (
                    'draft','submitted','noted','checked','reviewed',
                    'budget_checked','returned',
                    'approved','rejected','cancelled','converted_to_po'
                ))
        ");
    }

    public function down(): void
    {
        // Restore original constraint
        DB::statement('ALTER TABLE purchase_requests DROP CONSTRAINT chk_pr_status');
        DB::statement("
            ALTER TABLE purchase_requests
            ADD CONSTRAINT chk_pr_status
                CHECK (status IN ('draft','submitted','noted','checked','reviewed','approved','rejected','cancelled','converted_to_po'))
        ");

        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('budget_checked_by_id');
            $table->dropColumn(['budget_checked_at', 'budget_checked_comments']);
            $table->dropConstrainedForeignId('returned_by_id');
            $table->dropColumn(['returned_at', 'return_reason']);
        });
    }
};
