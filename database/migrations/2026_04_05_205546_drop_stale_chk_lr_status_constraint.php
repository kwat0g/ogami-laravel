<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The original create_leave_requests_table migration added chk_lr_status
 * with only ('draft','submitted','approved','rejected','cancelled').
 *
 * The rework_leave_request_workflow migration (2026_03_06) added
 * leave_requests_status_check with the full 4-step workflow statuses,
 * but mistakenly dropped leave_requests_status_check (which didn't exist yet)
 * instead of chk_lr_status. Both constraints ended up coexisting, and the
 * stale chk_lr_status blocks head_approved, manager_checked, and ga_processed.
 *
 * This migration drops the stale constraint. leave_requests_status_check
 * is already the authoritative constraint with the correct values.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE leave_requests DROP CONSTRAINT IF EXISTS chk_lr_status');
    }

    public function down(): void
    {
        // Restore only the statuses that were valid in the original constraint.
        // Note: restoring this would again block the 4-step workflow statuses.
        DB::statement("
            ALTER TABLE leave_requests ADD CONSTRAINT chk_lr_status
            CHECK (status IN ('draft','submitted','approved','rejected','cancelled'))
        ");
    }
};
