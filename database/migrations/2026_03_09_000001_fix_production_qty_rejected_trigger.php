<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the missing qty_rejected column to production_orders
        DB::statement('ALTER TABLE production_orders ADD COLUMN IF NOT EXISTS qty_rejected DECIMAL(15,4) NOT NULL DEFAULT 0');

        // 2. Replace the trigger function to also accumulate qty_rejected
        DB::statement('
            CREATE OR REPLACE FUNCTION trg_fn_update_production_qty()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                UPDATE production_orders
                SET qty_produced = qty_produced + NEW.qty_produced,
                    qty_rejected = qty_rejected + NEW.qty_rejected
                WHERE id = NEW.production_order_id;
                RETURN NEW;
            END;
            $$
        ');

        // 3. Back-fill qty_rejected for any existing orders from their logs
        DB::statement('
            UPDATE production_orders po
            SET qty_rejected = COALESCE((
                SELECT SUM(qty_rejected)
                FROM production_output_logs
                WHERE production_order_id = po.id
            ), 0)
        ');
    }

    public function down(): void
    {
        // Revert trigger to qty_produced only
        DB::statement('
            CREATE OR REPLACE FUNCTION trg_fn_update_production_qty()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                UPDATE production_orders
                SET qty_produced = qty_produced + NEW.qty_produced
                WHERE id = NEW.production_order_id;
                RETURN NEW;
            END;
            $$
        ');

        DB::statement('ALTER TABLE production_orders DROP COLUMN IF EXISTS qty_rejected');
    }
};
