<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5: Add delivery_receipt_id FK to delivery_schedules table.
 * Links DeliverySchedule directly to the DR created during dispatch,
 * replacing the indirect chain through CombinedDeliverySchedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_schedules', function (Blueprint $table): void {
            $table->unsignedBigInteger('delivery_receipt_id')->nullable()->after('combined_delivery_schedule_id');

            $table->foreign('delivery_receipt_id')
                ->references('id')
                ->on('delivery_receipts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_schedules', function (Blueprint $table): void {
            $table->dropForeign(['delivery_receipt_id']);
            $table->dropColumn('delivery_receipt_id');
        });
    }
};
