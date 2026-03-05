<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Task 1A — Step 2: Rename three existing roles.
 *
 * supervisor         → head
 * hr_manager         → manager
 * accounting_manager → officer
 *
 * model_has_roles pivot rows are FK-referenced by role_id, not role name,
 * so existing user assignments survive this rename automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'supervisor')
            ->where('guard_name', 'web')
            ->update(['name' => 'head']);

        DB::table('roles')
            ->where('name', 'hr_manager')
            ->where('guard_name', 'web')
            ->update(['name' => 'manager']);

        DB::table('roles')
            ->where('name', 'accounting_manager')
            ->where('guard_name', 'web')
            ->update(['name' => 'officer']);

        // No changes to model_has_roles — FK uses role_id, not name.
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'head')   ->where('guard_name', 'web')->update(['name' => 'supervisor']);
        DB::table('roles')->where('name', 'manager')->where('guard_name', 'web')->update(['name' => 'hr_manager']);
        DB::table('roles')->where('name', 'officer')->where('guard_name', 'web')->update(['name' => 'accounting_manager']);
    }
};
