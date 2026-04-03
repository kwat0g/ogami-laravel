<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE production_orders DROP CONSTRAINT IF EXISTS chk_po_source_type');
        DB::statement(<<<'SQL'
            ALTER TABLE production_orders ADD CONSTRAINT chk_po_source_type
            CHECK (source_type IN (
                'client_order',
                'sales_order',
                'delivery_schedule',
                'manual',
                'rework',
                'force_production',
                'replenishment'
            ))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE production_orders DROP CONSTRAINT IF EXISTS chk_po_source_type');
        DB::statement(<<<'SQL'
            ALTER TABLE production_orders ADD CONSTRAINT chk_po_source_type
            CHECK (source_type IN (
                'client_order',
                'sales_order',
                'delivery_schedule',
                'manual',
                'rework'
            ))
        SQL);
    }
};
