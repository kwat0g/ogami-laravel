<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Task 1A — Step 3: Insert the vice_president role.
 *
 * manager, officer, and head are already handled by the rename migration.
 * Only vice_president needs a brand-new row.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore([
            'name'       => 'vice_president',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'vice_president')
            ->where('guard_name', 'web')
            ->delete();
    }
};
