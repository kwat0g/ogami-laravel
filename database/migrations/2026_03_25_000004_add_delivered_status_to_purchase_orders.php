<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop and recreate the CHECK constraint to include 'delivered'
        DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_status_check');
        DB::statement("
            ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check
            CHECK (status IN (
                'draft','sent','negotiating','acknowledged','in_transit','delivered',
                'partially_received','fully_received','closed','cancelled'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_status_check');
        DB::statement("
            ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check
            CHECK (status IN (
                'draft','sent','negotiating','acknowledged','in_transit',
                'partially_received','fully_received','closed','cancelled'
            ))
        ");
    }
};
