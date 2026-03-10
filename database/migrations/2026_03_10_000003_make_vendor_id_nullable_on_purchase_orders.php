<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow vendor_id to be null on purchase_orders so that auto-created PO drafts
 * (from VP-approved PRs) can exist without a vendor until the Purchasing Officer assigns one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            // Drop the existing NOT NULL constraint + FK, then re-add as nullable
            $table->dropConstrainedForeignId('vendor_id');
            $table->foreignId('vendor_id')
                ->nullable()
                ->after('purchase_request_id')
                ->constrained('vendors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vendor_id');
            $table->foreignId('vendor_id')
                ->after('purchase_request_id')
                ->constrained('vendors');
        });
    }
};
