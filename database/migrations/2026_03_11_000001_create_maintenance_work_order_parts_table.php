<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C1: Maintenance ↔ Inventory spare parts integration.
 *
 * Records the spare parts planned for and consumed by a maintenance work order.
 * On WO completion, inventory stock is deducted via StockService::issue().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_work_order_parts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('work_order_id')
                ->constrained('maintenance_work_orders')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('item_masters')
                ->restrictOnDelete();

            $table->foreignId('location_id')
                ->constrained('warehouse_locations')
                ->restrictOnDelete();

            $table->decimal('qty_required', 10, 4)->default(1);
            $table->decimal('qty_consumed', 10, 4)->nullable()->comment('Set when WO is completed and stock issued');

            $table->string('remarks', 500)->nullable();
            $table->foreignId('added_by_id')->constrained('users');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE maintenance_work_order_parts ADD CONSTRAINT chk_mwop_qty_required CHECK (qty_required > 0)');
        DB::statement('ALTER TABLE maintenance_work_order_parts ADD CONSTRAINT chk_mwop_qty_consumed CHECK (qty_consumed IS NULL OR qty_consumed >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_work_order_parts');
    }
};
