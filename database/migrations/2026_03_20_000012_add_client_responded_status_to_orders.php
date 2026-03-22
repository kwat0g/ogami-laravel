<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing enum constraint and add client_responded status
        // PostgreSQL stores enum as check constraints
        DB::statement('ALTER TABLE client_orders DROP CONSTRAINT IF EXISTS client_orders_status_check');

        // Add new check constraint with all statuses including client_responded
        DB::statement("ALTER TABLE client_orders ADD CONSTRAINT client_orders_status_check CHECK (status IN ('pending', 'negotiating', 'client_responded', 'approved', 'rejected', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE client_orders DROP CONSTRAINT IF EXISTS client_orders_status_check');
        DB::statement("ALTER TABLE client_orders ADD CONSTRAINT client_orders_status_check CHECK (status IN ('pending', 'negotiating', 'approved', 'rejected', 'cancelled'))");
    }
};
