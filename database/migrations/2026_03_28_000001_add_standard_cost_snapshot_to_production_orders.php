<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add standard cost snapshot columns to production_orders.
 *
 * Real-world ERP pattern: when a Production Order is created, the BOM's
 * standard cost is frozen as a snapshot. This allows accurate cost variance
 * analysis even if the BOM cost changes later.
 *
 * Also adds estimated_total_cost_centavos = standard_unit_cost * qty_required,
 * giving production managers immediate cost visibility at PO creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('standard_unit_cost_centavos')
                ->default(0)
                ->after('qty_produced')
                ->comment('BOM standard cost per unit at PO creation time (frozen snapshot)');

            $table->unsignedBigInteger('estimated_total_cost_centavos')
                ->default(0)
                ->after('standard_unit_cost_centavos')
                ->comment('standard_unit_cost * qty_required (estimated total production cost)');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropColumn(['standard_unit_cost_centavos', 'estimated_total_cost_centavos']);
        });
    }
};
