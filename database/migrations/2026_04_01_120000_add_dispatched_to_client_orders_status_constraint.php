<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE client_orders DROP CONSTRAINT IF EXISTS client_orders_status_check');
        DB::statement("ALTER TABLE client_orders ADD CONSTRAINT client_orders_status_check CHECK (status IN ('pending','negotiating','client_responded','vp_pending','approved','in_production','ready_for_delivery','dispatched','delivered','fulfilled','rejected','cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE client_orders DROP CONSTRAINT IF EXISTS client_orders_status_check');
        DB::statement("ALTER TABLE client_orders ADD CONSTRAINT client_orders_status_check CHECK (status IN ('pending','negotiating','client_responded','vp_pending','approved','in_production','ready_for_delivery','delivered','fulfilled','rejected','cancelled'))");
    }
};
