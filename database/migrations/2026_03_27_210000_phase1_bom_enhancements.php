<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.1 — BOM enhancements for thesis-grade manufacturing ERP.
 *
 * 1. Add standard_cost_centavos + last_cost_rollup_at to bill_of_materials
 * 2. Add parent_bom_component_id to bom_components for multi-level BOM
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. BOM cost rollup columns
        if (! Schema::hasColumn('bill_of_materials', 'standard_cost_centavos')) {
            Schema::table('bill_of_materials', function (Blueprint $table): void {
                $table->unsignedBigInteger('standard_cost_centavos')->default(0)->after('standard_production_days');
                $table->timestamp('last_cost_rollup_at')->nullable()->after('standard_cost_centavos');
            });
        }

        // 2. Multi-level BOM support: self-referencing parent component
        if (! Schema::hasColumn('bom_components', 'parent_bom_component_id')) {
            Schema::table('bom_components', function (Blueprint $table): void {
                $table->foreignId('parent_bom_component_id')
                    ->nullable()
                    ->after('bom_id')
                    ->constrained('bom_components')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('bom_components', function (Blueprint $table): void {
            $table->dropForeign(['parent_bom_component_id']);
            $table->dropColumn('parent_bom_component_id');
        });

        Schema::table('bill_of_materials', function (Blueprint $table): void {
            $table->dropColumn(['standard_cost_centavos', 'last_cost_rollup_at']);
        });
    }
};
