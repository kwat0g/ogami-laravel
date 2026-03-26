<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->change();
            $table->string('payment_terms', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Reverting requires knowing if there are null values.
            // We assume for rollback that we might need to update them or let them fail if null exists.
            // But strict rollback:
            // $table->date('delivery_date')->nullable(false)->change();
            // $table->string('payment_terms', 50)->nullable(false)->change();
        });
    }
};
