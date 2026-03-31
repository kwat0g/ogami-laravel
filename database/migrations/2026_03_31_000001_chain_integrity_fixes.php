<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chain Process Integrity Fixes — P1, P3.
 *
 * P1: Add sales_order_id to delivery_receipts so outbound DRs
 *     can be traced back to their originating Sales Order.
 *
 * P3: Add purchase_order_id to vendor_invoices so AP invoices
 *     are linked to the originating PO (3-way match chain).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── P1: delivery_receipts.sales_order_id ─────────────────────────
        if (! Schema::hasColumn('delivery_receipts', 'sales_order_id')) {
            Schema::table('delivery_receipts', function (Blueprint $table) {
                $table->foreignId('sales_order_id')
                    ->nullable()
                    ->after('delivery_schedule_id')
                    ->constrained('sales_orders')
                    ->nullOnDelete();
            });
        }

        // ── P3: vendor_invoices.purchase_order_id ────────────────────────
        if (! Schema::hasColumn('vendor_invoices', 'purchase_order_id')) {
            Schema::table('vendor_invoices', function (Blueprint $table) {
                $table->foreignId('purchase_order_id')
                    ->nullable()
                    ->after('vendor_id')
                    ->constrained('purchase_orders')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_receipts', 'sales_order_id')) {
                $table->dropConstrainedForeignId('sales_order_id');
            }
        });

        Schema::table('vendor_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_invoices', 'purchase_order_id')) {
                $table->dropConstrainedForeignId('purchase_order_id');
            }
        });
    }
};
