<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_order_delivery_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_order_id')->constrained('client_orders')->cascadeOnDelete();
            $table->foreignId('client_order_item_id')->nullable()->constrained('client_order_items')->nullOnDelete();
            $table->foreignId('delivery_schedule_id')->constrained('delivery_schedules')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_order_id', 'delivery_schedule_id'], 'unique_order_schedule');
            $table->index('client_order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_order_delivery_schedules');
    }
};
