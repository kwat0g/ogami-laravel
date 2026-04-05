<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE production_orders DROP CONSTRAINT IF EXISTS chk_prod_order_status');
        DB::statement(<<<'SQL'
            ALTER TABLE production_orders ADD CONSTRAINT chk_prod_order_status
            CHECK (status IN ('draft','released','in_progress','on_hold','completed','closed','cancelled'))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE production_orders DROP CONSTRAINT IF EXISTS chk_prod_order_status');
        DB::statement(<<<'SQL'
            ALTER TABLE production_orders ADD CONSTRAINT chk_prod_order_status
            CHECK (status IN ('draft','released','in_progress','on_hold','completed','cancelled'))
        SQL);
    }
};
