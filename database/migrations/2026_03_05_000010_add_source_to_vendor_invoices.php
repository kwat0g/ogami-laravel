<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4: Add source tracking + procurement linkage to vendor_invoices.
 *
 * source: 'manual' (default) | 'auto_procurement' — distinguishes auto-created
 *         AP invoices from those entered by the Accounting Officer.
 * purchase_order_id: nullable FK back to the originating PO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->string('source', 30)->default('manual')->after('description');
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->after('source')
                ->constrained('purchase_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropColumn(['source', 'purchase_order_id']);
        });
    }
};
