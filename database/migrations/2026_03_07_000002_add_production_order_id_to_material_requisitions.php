<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds production_order_id FK to material_requisitions.
 * Also relaxes department_id to nullable so auto-generated MRQs
 * (created from a BOM on production order release) don't require
 * a manual department selection.
 *
 * PROD-002: Releasing a production order auto-generates a draft MRQ
 *           that must be approved before materials can be issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the NOT NULL constraint on department_id by making it nullable
        DB::statement('ALTER TABLE material_requisitions ALTER COLUMN department_id DROP NOT NULL');

        Schema::table('material_requisitions', function (Blueprint $table): void {
            $table->foreignId('production_order_id')
                ->nullable()
                ->after('department_id')
                ->constrained('production_orders')
                ->nullOnDelete();

            $table->index('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('material_requisitions', function (Blueprint $table): void {
            $table->dropForeign(['production_order_id']);
            $table->dropColumn('production_order_id');
        });

        // Restore NOT NULL on department_id
        DB::statement('UPDATE material_requisitions SET department_id = 1 WHERE department_id IS NULL');
        DB::statement('ALTER TABLE material_requisitions ALTER COLUMN department_id SET NOT NULL');
    }
};
