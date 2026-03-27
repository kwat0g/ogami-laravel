<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.7 — CAPA approval workflow - update status CHECK constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE capa_actions DROP CONSTRAINT IF EXISTS chk_capa_actions_status');
        DB::statement("ALTER TABLE capa_actions ADD CONSTRAINT chk_capa_actions_status CHECK (status IN ('draft','assigned','in_progress','verification','closed','rejected'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE capa_actions DROP CONSTRAINT IF EXISTS chk_capa_actions_status');
    }
};
