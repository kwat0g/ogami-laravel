<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE maintenance_work_orders
            ADD COLUMN IF NOT EXISTS labor_hours NUMERIC(8,2)
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE maintenance_work_orders
            DROP COLUMN IF EXISTS labor_hours
        SQL);
    }
};
