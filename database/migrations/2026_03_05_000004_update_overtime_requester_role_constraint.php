<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Task 1A — Step 7: Update overtime_requests.requester_role CHECK constraint.
 *
 * Old values allowed: staff, supervisor, manager
 * New values allowed: staff, head, manager, officer, vice_president
 *
 * Existing 'supervisor' rows are updated to 'head' to match the role rename.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing data rows first
        DB::table('overtime_requests')
            ->where('requester_role', 'supervisor')
            ->update(['requester_role' => 'head']);

        // Drop old constraint and add new one
        DB::statement('ALTER TABLE overtime_requests DROP CONSTRAINT IF EXISTS overtime_requests_requester_role_check');
        DB::statement("
            ALTER TABLE overtime_requests
            ADD CONSTRAINT overtime_requests_requester_role_check
            CHECK (requester_role IN ('staff', 'head', 'manager', 'officer', 'vice_president'))
        ");
    }

    public function down(): void
    {
        // Revert data
        DB::table('overtime_requests')
            ->where('requester_role', 'head')
            ->update(['requester_role' => 'supervisor']);

        DB::statement('ALTER TABLE overtime_requests DROP CONSTRAINT IF EXISTS overtime_requests_requester_role_check');
        DB::statement("
            ALTER TABLE overtime_requests
            ADD CONSTRAINT overtime_requests_requester_role_check
            CHECK (requester_role IN ('staff', 'supervisor', 'manager'))
        ");
    }
};
