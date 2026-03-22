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
            $table->json('client_acknowledgment')->nullable()->after('notes')
                ->comment('JSON containing client acknowledgment details: received_qty, condition, notes, acknowledged_at, acknowledged_by');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_schedules', function (Blueprint $table): void {
            $table->dropColumn('client_acknowledgment');
        });
    }
};
