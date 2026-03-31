<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Expand vehicle type CHECK to include pickup and trailer
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT IF EXISTS chk_vehicle_type');
        DB::statement("
            ALTER TABLE vehicles
            ADD CONSTRAINT chk_vehicle_type
            CHECK (type IN ('truck','van','pickup','motorcycle','trailer','other'))
        ");

        // Expand vehicle status CHECK to include decommissioned
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT IF EXISTS chk_vehicle_status');
        DB::statement("
            ALTER TABLE vehicles
            ADD CONSTRAINT chk_vehicle_status
            CHECK (status IN ('active','inactive','maintenance','decommissioned'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT IF EXISTS chk_vehicle_type');
        DB::statement("
            ALTER TABLE vehicles
            ADD CONSTRAINT chk_vehicle_type
            CHECK (type IN ('truck','van','motorcycle','other'))
        ");

        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT IF EXISTS chk_vehicle_status');
        DB::statement("
            ALTER TABLE vehicles
            ADD CONSTRAINT chk_vehicle_status
            CHECK (status IN ('active','inactive','maintenance'))
        ");
    }
};
