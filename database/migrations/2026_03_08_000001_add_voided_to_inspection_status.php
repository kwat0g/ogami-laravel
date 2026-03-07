<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old constraint and re-create with 'voided' included
        DB::statement('ALTER TABLE inspections DROP CONSTRAINT IF EXISTS chk_inspection_status');
        DB::statement("ALTER TABLE inspections ADD CONSTRAINT chk_inspection_status CHECK (status IN ('open','passed','failed','on_hold','voided'))");
    }

    public function down(): void
    {
        // Revert any voided records back to on_hold before removing the status
        DB::table('inspections')->where('status', 'voided')->update(['status' => 'on_hold']);

        DB::statement('ALTER TABLE inspections DROP CONSTRAINT IF EXISTS chk_inspection_status');
        DB::statement("ALTER TABLE inspections ADD CONSTRAINT chk_inspection_status CHECK (status IN ('open','passed','failed','on_hold'))");
    }
};
