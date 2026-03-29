<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD-S01: BOM version snapshot on production order creation.
 *
 * Stores a JSON snapshot of BOM components at the time the production order
 * is created, so that subsequent BOM changes don't affect historical orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->jsonb('bom_snapshot')->nullable()->after('bom_id')
                ->comment('JSON snapshot of BOM components at order creation time');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropColumn('bom_snapshot');
        });
    }
};
