<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_orders', function (Blueprint $table): void {
            $table->foreignId('vp_approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('approved_by');

            $table->timestamp('vp_approved_at')->nullable()->after('vp_approved_by');

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('rejected_at');

            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');

            $table->timestamp('sla_deadline')->nullable()->after('last_negotiation_at')
                ->comment('Negotiation SLA deadline — order is flagged as stale if this passes without resolution');
        });

        // Expand the status CHECK constraint to include 'vp_pending'
        DB::statement('ALTER TABLE client_orders DROP CONSTRAINT IF EXISTS client_orders_status_check');
        DB::statement("ALTER TABLE client_orders ADD CONSTRAINT client_orders_status_check CHECK (status IN ('pending','negotiating','client_responded','vp_pending','approved','rejected','cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE client_orders DROP CONSTRAINT IF EXISTS client_orders_status_check');
        DB::statement("ALTER TABLE client_orders ADD CONSTRAINT client_orders_status_check CHECK (status IN ('pending','negotiating','client_responded','approved','rejected','cancelled'))");

        Schema::table('client_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vp_approved_by');
            $table->dropColumn('vp_approved_at');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancelled_at');
            $table->dropColumn('sla_deadline');
        });
    }
};
