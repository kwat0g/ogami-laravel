<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_order_id')->constrained()->onDelete('cascade');
            
            // Item details
            $table->foreignId('item_master_id')->constrained('item_masters')->comment('Product being ordered');
            $table->string('item_description')->comment('Description at time of order');
            $table->decimal('quantity', 15, 4)->comment('Quantity ordered');
            $table->string('unit_of_measure', 50)->comment('UOM at time of order');
            $table->bigInteger('unit_price_centavos')->comment('Price per unit in centavos');
            $table->bigInteger('line_total_centavos')->comment('quantity * unit_price in centavos');
            
            // Negotiation tracking per line
            $table->decimal('negotiated_quantity', 15, 4)->nullable()->comment('Proposed different quantity');
            $table->bigInteger('negotiated_price_centavos')->nullable()->comment('Proposed different price');
            $table->string('line_notes')->nullable()->comment('Client notes for this line item');
            
            $table->integer('line_order')->default(0)->comment('For ordering items in display');
            $table->timestamps();
            
            // Indexes
            $table->index(['client_order_id', 'line_order']);
            $table->index('item_master_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_order_items');
    }
};
