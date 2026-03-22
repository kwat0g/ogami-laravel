<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add 'in_transit' status to purchase_orders table for vendor delivery tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the existing CHECK constraint
        DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS chk_po_status');

        // Add the updated CHECK constraint including 'in_transit'
        DB::statement(<<<'SQL'
            ALTER TABLE purchase_orders
            ADD CONSTRAINT chk_po_status
            CHECK (status IN ('draft','sent','in_transit','partially_received','fully_received','closed','cancelled'))
        SQL);
    }

    public function down(): void
    {
        // Revert to original constraint
        DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS chk_po_status');

        DB::statement(<<<'SQL'
            ALTER TABLE purchase_orders
            ADD CONSTRAINT chk_po_status
            CHECK (status IN ('draft','sent','partially_received','fully_received','closed','cancelled'))
        SQL);
    }
};
