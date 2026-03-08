<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table): void {
            $table->foreignId('delivery_schedule_id')
                ->nullable()
                ->default(null)
                ->after('customer_id')
                ->constrained('delivery_schedules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('delivery_schedule_id');
        });
    }
};
