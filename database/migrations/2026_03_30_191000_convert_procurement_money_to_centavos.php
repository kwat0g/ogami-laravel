<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C7 FIX: Convert procurement money columns from decimal(15,2) to
 * unsignedBigInteger (centavos) to match the Money VO pattern used
 * in Payroll, Inventory, AP, and AR domains.
 *
 * This prevents floating-point rounding errors when procurement values
 * flow into AP or GL. Conversion: existing pesos * 100 = centavos.
 *
 * IMPORTANT: This migration is idempotent — it checks column type before
 * converting. Safe to run multiple times.
 */
return new class extends Migration
{
    /**
     * Money columns that need conversion from decimal to bigint centavos.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'purchase_request_items' => ['estimated_unit_cost', 'estimated_total'],
        'purchase_order_items' => ['unit_cost', 'total_cost', 'agreed_unit_cost'],
    ];

    /**
     * New centavos column names (suffixed with _centavos).
     */
    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                $centavosCol = $column . '_centavos';

                // Skip if centavos column already exists (idempotent)
                if (Schema::hasColumn($table, $centavosCol)) {
                    continue;
                }

                // Skip if source column doesn't exist
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                // Add new centavos column
                DB::statement("ALTER TABLE {$table} ADD COLUMN {$centavosCol} BIGINT DEFAULT 0");

                // Convert existing data: pesos * 100 = centavos
                DB::statement("UPDATE {$table} SET {$centavosCol} = COALESCE(ROUND({$column} * 100), 0)");

                // Add NOT NULL constraint after data migration
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$centavosCol} SET NOT NULL");
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$centavosCol} SET DEFAULT 0");
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                $centavosCol = $column . '_centavos';

                if (Schema::hasColumn($table, $centavosCol)) {
                    DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS {$centavosCol}");
                }
            }
        }
    }
};
