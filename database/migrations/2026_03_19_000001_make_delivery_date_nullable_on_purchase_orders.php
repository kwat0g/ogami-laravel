<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow delivery_date to be null on purchase_orders so that auto-created PO drafts
 * (from VP-approved PRs) can exist without a delivery date until the Purchasing Officer
 * sets it when sending to vendor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            // Make delivery_date nullable
            $table->date('delivery_date')->nullable()->change();
        });

        // Update the check constraint to allow null delivery_date
        DB::statement('
            ALTER TABLE purchase_orders
            DROP CONSTRAINT IF EXISTS chk_po_delivery_after_po_date
        ');

        DB::statement('
            ALTER TABLE purchase_orders
            ADD CONSTRAINT chk_po_delivery_after_po_date
                CHECK (delivery_date IS NULL OR delivery_date >= po_date)
        ');
    }

    public function down(): void
    {
        // Revert to NOT NULL (will fail if there are null values)
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->date('delivery_date')->nullable(false)->change();
        });

        DB::statement('
            ALTER TABLE purchase_orders
            DROP CONSTRAINT IF EXISTS chk_po_delivery_after_po_date
        ');

        DB::statement('
            ALTER TABLE purchase_orders
            ADD CONSTRAINT chk_po_delivery_after_po_date
                CHECK (delivery_date >= po_date)
        ');
    }
};
