<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MOLD-MAINT-001: Allow Maintenance Work Orders to be created for molds
 * (which are not Equipment records) by:
 *   1. Making equipment_id nullable (mold PM WOs have no Equipment FK).
 *   2. Adding a nullable mold_master_id FK for mold-triggered WOs.
 *   3. Adding a CHECK so every WO references either equipment OR mold.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE maintenance_work_orders
                ALTER COLUMN equipment_id DROP NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE maintenance_work_orders
                ADD COLUMN mold_master_id BIGINT
                    REFERENCES mold_masters(id) ON DELETE SET NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE maintenance_work_orders
                ADD CONSTRAINT chk_mwo_has_asset
                    CHECK (equipment_id IS NOT NULL OR mold_master_id IS NOT NULL)
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE maintenance_work_orders
                DROP CONSTRAINT IF EXISTS chk_mwo_has_asset
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE maintenance_work_orders
                DROP COLUMN IF EXISTS mold_master_id
        SQL);

        // Remove rows where equipment_id is null (mold work orders) before
        // restoring the NOT NULL constraint — otherwise rollback will crash.
        DB::statement(<<<'SQL'
            DELETE FROM maintenance_work_orders WHERE equipment_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE maintenance_work_orders
                ALTER COLUMN equipment_id SET NOT NULL
        SQL);
    }
};
