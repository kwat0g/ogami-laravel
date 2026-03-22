<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('item_masters')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->decimal('quantity_reserved', 15, 4);
            $table->string('reservation_type', 30); // production_order, delivery_schedule, safety_stock
            $table->foreignId('reference_id'); // PO ID or DS ID
            $table->string('reference_type'); // ProductionOrder::class or DeliverySchedule::class
            $table->string('status', 20)->default('active'); // active, fulfilled, cancelled, expired
            $table->timestamp('reserved_at');
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status']);
            $table->index(['reservation_type', 'status']);
            $table->index(['reference_id', 'reference_type']);
            $table->index(['expires_at', 'status']);
        });

        // Add reserved_quantity column to stock_balances for quick lookup
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->decimal('quantity_reserved', 15, 4)->default(0)->after('quantity_on_hand');
        });
    }

    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropColumn('quantity_reserved');
        });
        Schema::dropIfExists('stock_reservations');
    }
};
