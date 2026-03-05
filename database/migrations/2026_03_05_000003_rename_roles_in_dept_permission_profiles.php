<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Task 1A — Step 4: Rename role values in department_permission_profiles.
 *
 * The `role` column is a plain varchar — no ALTER TYPE needed.
 * Three renames mirror the roles table migration:
 *   supervisor         → head
 *   hr_manager         → manager
 *   accounting_manager → officer
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('department_permission_profiles')
            ->where('role', 'supervisor')
            ->update(['role' => 'head']);

        DB::table('department_permission_profiles')
            ->where('role', 'hr_manager')
            ->update(['role' => 'manager']);

        DB::table('department_permission_profiles')
            ->where('role', 'accounting_manager')
            ->update(['role' => 'officer']);
    }

    public function down(): void
    {
        DB::table('department_permission_profiles')
            ->where('role', 'head')
            ->update(['role' => 'supervisor']);

        DB::table('department_permission_profiles')
            ->where('role', 'manager')
            ->update(['role' => 'hr_manager']);

        DB::table('department_permission_profiles')
            ->where('role', 'officer')
            ->update(['role' => 'accounting_manager']);
    }
};
