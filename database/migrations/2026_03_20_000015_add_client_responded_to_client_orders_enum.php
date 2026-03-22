<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Updates the status enum to include 'client_responded' status
     * for the two-way negotiation workflow.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // For PostgreSQL, Laravel creates an enum type named "table_column"
            // Check if client_responded already exists in the client_orders_status enum
            $enumTypeName = 'client_orders_status';

            // First check if the enum type exists
            $typeExists = DB::selectOne('SELECT 1 FROM pg_type WHERE typname = ?', [$enumTypeName]);

            if ($typeExists) {
                // Check if client_responded already exists
                $labelExists = DB::selectOne("SELECT 1 FROM pg_enum WHERE enumtypid = ?::regtype AND enumlabel = 'client_responded'", [$enumTypeName]);

                if (! $labelExists) {
                    // Add the new enum value
                    DB::statement("ALTER TYPE {$enumTypeName} ADD VALUE 'client_responded' AFTER 'negotiating'");
                }
            }
        } else {
            // For MySQL, we need to recreate the enum
            DB::statement("ALTER TABLE client_orders MODIFY COLUMN status ENUM('pending', 'negotiating', 'client_responded', 'approved', 'rejected', 'cancelled') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: Removing enum values is not straightforward in PostgreSQL
     * We can only remove if no rows use this status
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            // For MySQL
            DB::statement("ALTER TABLE client_orders MODIFY COLUMN status ENUM('pending', 'negotiating', 'approved', 'rejected', 'cancelled') DEFAULT 'pending'");
        }
        // For PostgreSQL, we cannot easily remove enum values
        // They would need to be renamed/migrated
    }
};
