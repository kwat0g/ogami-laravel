<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_schedules', function (Blueprint $table): void {
            $table->decimal('unit_price', 15, 4)->nullable()->default(null)->after('qty_ordered');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_schedules', function (Blueprint $table): void {
            $table->dropColumn('unit_price');
        });
    }
};
