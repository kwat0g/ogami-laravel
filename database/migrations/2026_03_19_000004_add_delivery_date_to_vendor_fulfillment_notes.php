<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_fulfillment_notes', function (Blueprint $table): void {
            $table->date('delivery_date')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_fulfillment_notes', function (Blueprint $table): void {
            $table->dropColumn('delivery_date');
        });
    }
};
