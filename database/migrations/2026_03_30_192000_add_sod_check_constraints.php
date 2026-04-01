<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M8 FIX: Add database-level SoD CHECK constraints.
 *
 * These constraints enforce Segregation of Duties at the DB level,
 * making SoD bypass impossible even through direct SQL or service
 * layer bugs. Belt-and-suspenders with the policy/service layer checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Purchase Requests: reviewer cannot be the creator
        if (Schema::hasTable('purchase_requests')
            && Schema::hasColumn('purchase_requests', 'requested_by_id')
            && Schema::hasColumn('purchase_requests', 'reviewed_by_id')) {
            DB::statement('ALTER TABLE purchase_requests DROP CONSTRAINT IF EXISTS chk_pr_sod_reviewer');

            // Normalize legacy rows that violate SoD before adding a validated CHECK.
            DB::statement('UPDATE purchase_requests SET reviewed_by_id = NULL WHERE reviewed_by_id IS NOT NULL AND reviewed_by_id = requested_by_id');

            DB::statement('
                ALTER TABLE purchase_requests
                ADD CONSTRAINT chk_pr_sod_reviewer
                CHECK (reviewed_by_id IS NULL OR reviewed_by_id != requested_by_id)
            ');
        }

        // Purchase Requests: budget verifier cannot be the reviewer
        if (Schema::hasTable('purchase_requests')
            && Schema::hasColumn('purchase_requests', 'reviewed_by_id')
            && Schema::hasColumn('purchase_requests', 'budget_checked_by_id')) {
            DB::statement('ALTER TABLE purchase_requests DROP CONSTRAINT IF EXISTS chk_pr_sod_budget');

            // Normalize legacy rows that violate SoD before adding a validated CHECK.
            DB::statement('UPDATE purchase_requests SET budget_checked_by_id = NULL WHERE budget_checked_by_id IS NOT NULL AND reviewed_by_id IS NOT NULL AND budget_checked_by_id = reviewed_by_id');

            DB::statement('
                ALTER TABLE purchase_requests
                ADD CONSTRAINT chk_pr_sod_budget
                CHECK (budget_checked_by_id IS NULL OR budget_checked_by_id != reviewed_by_id)
            ');
        }

        // Journal Entries: poster cannot be the creator
        if (Schema::hasTable('journal_entries')
            && Schema::hasColumn('journal_entries', 'created_by')
            && Schema::hasColumn('journal_entries', 'posted_by')) {
            DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS chk_je_sod_poster');

            // Normalize legacy rows that violate SoD before adding a validated CHECK.
            DB::statement('UPDATE journal_entries SET posted_by = NULL WHERE posted_by IS NOT NULL AND posted_by = created_by');

            DB::statement('
                ALTER TABLE journal_entries
                ADD CONSTRAINT chk_je_sod_poster
                CHECK (posted_by IS NULL OR posted_by != created_by)
            ');
        }

        // Customer Invoices (AR): approver cannot be the creator
        if (Schema::hasTable('customer_invoices')
            && Schema::hasColumn('customer_invoices', 'created_by')
            && Schema::hasColumn('customer_invoices', 'approved_by')) {
            DB::statement('ALTER TABLE customer_invoices DROP CONSTRAINT IF EXISTS chk_ar_sod_approver');

            // Normalize legacy rows that violate SoD before adding a validated CHECK.
            DB::statement('UPDATE customer_invoices SET approved_by = NULL WHERE approved_by IS NOT NULL AND approved_by = created_by');

            DB::statement('
                ALTER TABLE customer_invoices
                ADD CONSTRAINT chk_ar_sod_approver
                CHECK (approved_by IS NULL OR approved_by != created_by)
            ');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE purchase_requests DROP CONSTRAINT IF EXISTS chk_pr_sod_reviewer');
        DB::statement('ALTER TABLE purchase_requests DROP CONSTRAINT IF EXISTS chk_pr_sod_budget');
        DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS chk_je_sod_poster');
        DB::statement('ALTER TABLE customer_invoices DROP CONSTRAINT IF EXISTS chk_ar_sod_approver');
    }
};
