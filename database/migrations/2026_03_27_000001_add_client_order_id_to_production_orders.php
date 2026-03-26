<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->foreignId('client_order_id')
                ->nullable()
                ->after('delivery_schedule_id')
                ->constrained('client_orders')
                ->nullOnDelete()
                ->comment('Originating client order for auto-created production orders');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropForeign(['client_order_id']);
            $table->dropColumn('client_order_id');
        });
    }
};
