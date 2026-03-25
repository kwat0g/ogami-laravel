<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->date('proposed_delivery_date')->nullable()->after('tracking_number');
            $table->string('proposed_payment_terms', 100)->nullable()->after('proposed_delivery_date');
            $table->decimal('original_total_po_amount', 15, 2)->nullable()->after('proposed_payment_terms');
            $table->boolean('requires_budget_recheck')->default(false)->after('original_total_po_amount');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'proposed_delivery_date',
                'proposed_payment_terms',
                'original_total_po_amount',
                'requires_budget_recheck',
            ]);
        });
    }
};
