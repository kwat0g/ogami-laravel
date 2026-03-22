<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combined_delivery_schedules', function (Blueprint $table): void {
            $table->boolean('has_dispute')->default(false)->after('missing_items')
                ->comment('True when client acknowledgment includes damaged or missing items');

            $table->json('dispute_summary')->nullable()->after('has_dispute')
                ->comment('JSON summary of disputed items: [{item_id, condition, received_qty, notes}]');

            $table->timestamp('dispute_resolved_at')->nullable()->after('dispute_summary');

            $table->foreignId('dispute_resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('dispute_resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('combined_delivery_schedules', function (Blueprint $table): void {
            $table->dropColumn(['has_dispute', 'dispute_summary', 'dispute_resolved_at']);
            $table->dropConstrainedForeignId('dispute_resolved_by');
        });
    }
};
