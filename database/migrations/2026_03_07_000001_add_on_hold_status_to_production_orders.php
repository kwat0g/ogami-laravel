<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds on_hold status + hold_reason column to production_orders.
 * QC-001: A failed QC inspection places the linked work order on hold
 *         until the non-conformance is resolved via NCR/CAPA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->text('hold_reason')->nullable()->after('notes');
        });

        // Update CHECK constraint to allow 'on_hold' status
        DB::statement('ALTER TABLE production_orders DROP CONSTRAINT IF EXISTS chk_prod_order_status');
        DB::statement(<<<'SQL'
            ALTER TABLE production_orders ADD CONSTRAINT chk_prod_order_status
            CHECK (status IN ('draft','released','in_progress','on_hold','completed','cancelled'))
        SQL);
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropColumn('hold_reason');
        });

        DB::statement('ALTER TABLE production_orders DROP CONSTRAINT IF EXISTS chk_prod_order_status');
        DB::statement(<<<'SQL'
            ALTER TABLE production_orders ADD CONSTRAINT chk_prod_order_status
            CHECK (status IN ('draft','released','in_progress','completed','cancelled'))
        SQL);
    }
};
