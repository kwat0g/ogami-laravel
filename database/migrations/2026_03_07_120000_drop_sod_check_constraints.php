<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop all database-level SoD CHECK constraints.
 *
 * Rationale: SoD is enforced at the application-service layer, where the
 * super_admin role can be given a bypass for testing/administrative purposes.
 * PostgreSQL CHECK constraints cannot inspect session context (user role), so
 * they cannot distinguish a super_admin who legitimately needs to progress all
 * workflow stages from a true SoD violation.
 */
return new class extends Migration
{
    public function up(): void
    {
        // purchase_requests
        DB::statement('ALTER TABLE purchase_requests DROP CONSTRAINT IF EXISTS chk_pr_sod_noted');
        DB::statement('ALTER TABLE purchase_requests DROP CONSTRAINT IF EXISTS chk_pr_sod_checked');
        DB::statement('ALTER TABLE purchase_requests DROP CONSTRAINT IF EXISTS chk_pr_sod_reviewed');
        DB::statement('ALTER TABLE purchase_requests DROP CONSTRAINT IF EXISTS chk_pr_sod_vp');

        // material_requisitions
        DB::statement('ALTER TABLE material_requisitions DROP CONSTRAINT IF EXISTS chk_sod_mrq_head');
        DB::statement('ALTER TABLE material_requisitions DROP CONSTRAINT IF EXISTS chk_sod_mrq_manager');
        DB::statement('ALTER TABLE material_requisitions DROP CONSTRAINT IF EXISTS chk_sod_mrq_officer');
        DB::statement('ALTER TABLE material_requisitions DROP CONSTRAINT IF EXISTS chk_sod_mrq_vp');

        // loans
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_loan_sod');
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_sod_loan_head');
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_sod_loan_manager');
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_sod_loan_officer');
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_sod_loan_vp');

        // leave_requests
        DB::statement('ALTER TABLE leave_requests DROP CONSTRAINT IF EXISTS chk_lr_sod');

        // bank_reconciliations
        DB::statement('ALTER TABLE bank_reconciliations DROP CONSTRAINT IF EXISTS chk_bank_recon_sod');

        // journal_entries
        DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS chk_sod_je_posting');

        // payroll_runs (three generations of naming)
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_sod_payroll');
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_sod_payroll_hr');
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_sod_payroll_acctg');
    }

    public function down(): void
    {
        // Restore purchase_requests SoD constraints
        DB::statement('ALTER TABLE purchase_requests ADD CONSTRAINT chk_pr_sod_noted CHECK (noted_by_id IS NULL OR noted_by_id <> submitted_by_id)');
        DB::statement('ALTER TABLE purchase_requests ADD CONSTRAINT chk_pr_sod_checked CHECK (checked_by_id IS NULL OR checked_by_id <> noted_by_id)');
        DB::statement('ALTER TABLE purchase_requests ADD CONSTRAINT chk_pr_sod_reviewed CHECK (reviewed_by_id IS NULL OR reviewed_by_id <> checked_by_id)');
        DB::statement('ALTER TABLE purchase_requests ADD CONSTRAINT chk_pr_sod_vp CHECK (vp_approved_by_id IS NULL OR vp_approved_by_id <> reviewed_by_id)');
    }
};
