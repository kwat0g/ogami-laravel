<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds item_master_id FK to purchase_request_items so the item identity
 * chain is preserved from MRQ -> PR -> PO -> GR without relying on
 * fragile name-matching.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->foreignId('item_master_id')
                ->nullable()
                ->after('vendor_item_id')
                ->constrained('item_masters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->dropForeign(['item_master_id']);
            $table->dropColumn('item_master_id');
        });
    }
};
