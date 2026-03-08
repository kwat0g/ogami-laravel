<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add item_master_id to purchase_order_items.
 *
 * The Purchasing Officer must link each PO line to an Item Master record
 * so that when GRs are confirmed the stock update has a guaranteed FK —
 * no fragile free-text name-matching required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('item_master_id')
                ->nullable()
                ->after('pr_item_id')
                ->constrained('item_masters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['item_master_id']);
            $table->dropColumn('item_master_id');
        });
    }
};
