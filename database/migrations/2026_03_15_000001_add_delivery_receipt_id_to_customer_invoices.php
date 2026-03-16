<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HIGH-001: Add delivery_receipt_id to customer_invoices for delivery verification.
 *
 * This links AR invoices to delivery receipts, enforcing that invoices
 * can only be created after goods have been delivered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('delivery_receipt_id')->nullable()->after('customer_id');
            $table->foreign('delivery_receipt_id')
                ->references('id')
                ->on('delivery_receipts')
                ->nullOnDelete()
                ->comment('Link to delivery receipt - enforces delivery-before-invoice');

            $table->index('delivery_receipt_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table): void {
            $table->dropForeign(['delivery_receipt_id']);
            $table->dropIndex(['delivery_receipt_id']);
            $table->dropColumn('delivery_receipt_id');
        });
    }
};
