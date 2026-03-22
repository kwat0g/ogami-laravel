<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Simplify PR workflow to 3-stage: Draft → Pending Review → Reviewed → Budget Verified → Approved
 * 
 * Removes: submitted, noted, checked, budget_checked
 * Adds: pending_review, budget_verified
 */
return new class extends Migration
{
    public function up(): void
    {
        // First, map old statuses to new ones
        DB::statement("
            UPDATE purchase_requests 
            SET status = CASE status
                WHEN 'submitted' THEN 'pending_review'
                WHEN 'noted' THEN 'pending_review'
                WHEN 'checked' THEN 'reviewed'
                WHEN 'budget_checked' THEN 'budget_verified'
                ELSE status
            END
            WHERE status IN ('submitted', 'noted', 'checked', 'budget_checked')
        ");

        // Update the CHECK constraint with new statuses
        DB::statement('ALTER TABLE purchase_requests DROP CONSTRAINT IF EXISTS chk_pr_status');
        DB::statement("
            ALTER TABLE purchase_requests
            ADD CONSTRAINT chk_pr_status
                CHECK (status IN (
                    'draft',
                    'pending_review',
                    'reviewed',
                    'budget_verified',
                    'returned',
                    'approved',
                    'rejected',
                    'cancelled',
                    'converted_to_po'
                ))
        ");
    }

    public function down(): void
    {
        // Map new statuses back to old ones (best effort)
        DB::statement("
            UPDATE purchase_requests 
            SET status = CASE status
                WHEN 'pending_review' THEN 'submitted'
                WHEN 'budget_verified' THEN 'budget_checked'
                ELSE status
            END
            WHERE status IN ('pending_review', 'budget_verified')
        ");

        // Restore old constraint
        DB::statement('ALTER TABLE purchase_requests DROP CONSTRAINT IF EXISTS chk_pr_status');
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
};
