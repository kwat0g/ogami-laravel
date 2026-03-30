<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->string('source_type', 30)->nullable()->after('client_order_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->foreignId('sales_order_id')->nullable()->after('source_id')
                ->constrained('sales_orders')->nullOnDelete();
        });

        DB::statement("ALTER TABLE production_orders ADD CONSTRAINT chk_po_source_type CHECK (source_type IN ('client_order', 'sales_order', 'delivery_schedule', 'manual', 'rework'))");

        // Backfill source_type for existing records based on FK presence
        DB::statement("UPDATE production_orders SET source_type = 'client_order' WHERE client_order_id IS NOT NULL AND source_type IS NULL");
        DB::statement("UPDATE production_orders SET source_type = 'delivery_schedule' WHERE delivery_schedule_id IS NOT NULL AND client_order_id IS NULL AND source_type IS NULL");
        DB::statement("UPDATE production_orders SET source_type = 'manual' WHERE source_type IS NULL");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE production_orders DROP CONSTRAINT IF EXISTS chk_po_source_type');

        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropForeign(['sales_order_id']);
            $table->dropColumn(['source_type', 'source_id', 'sales_order_id']);
        });
    }
};
