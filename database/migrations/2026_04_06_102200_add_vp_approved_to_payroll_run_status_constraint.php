<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add VP_APPROVED to the payroll_runs status CHECK constraint.
 *
 * The original `chk_pr_status` (from 2026_02_27_000001) omitted VP_APPROVED,
 * causing a CHECK violation when PayrollWorkflowService::vpApprove() transitions
 * ACCTG_APPROVED → VP_APPROVED.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_pr_status');
        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_pr_status
            CHECK (status IN (
                'DRAFT','SCOPE_SET','PRE_RUN_CHECKED',
                'PROCESSING','COMPUTED','REVIEW',
                'SUBMITTED','HR_APPROVED','ACCTG_APPROVED',
                'VP_APPROVED','DISBURSED','PUBLISHED',
                'FAILED','RETURNED','REJECTED',
                -- legacy lowercase values kept for migration safety
                'draft','locked','processing','completed',
                'submitted','approved','posted','failed','cancelled'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_pr_status');
        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_pr_status
            CHECK (status IN (
                'DRAFT','SCOPE_SET','PRE_RUN_CHECKED',
                'PROCESSING','COMPUTED','REVIEW',
                'SUBMITTED','HR_APPROVED','ACCTG_APPROVED',
                'DISBURSED','PUBLISHED',
                'FAILED','RETURNED','REJECTED',
                'draft','locked','processing','completed',
                'submitted','approved','posted','failed','cancelled'
            ))
        ");
    }
};
