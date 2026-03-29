<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Work locations with geofence configuration.
 *
 * Each location has a GPS coordinate (lat/lon) and a radius in meters
 * defining the geofence. Employees assigned to a work location must
 * clock in within the geofence (or provide an override reason).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_locations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->text('address');
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(100);
            $table->unsignedSmallInteger('allowed_variance_meters')->default(20);
            $table->boolean('is_remote_allowed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        DB::statement('ALTER TABLE work_locations ADD CONSTRAINT chk_wl_radius
            CHECK (radius_meters BETWEEN 10 AND 5000)');

        DB::statement('ALTER TABLE work_locations ADD CONSTRAINT chk_wl_latitude
            CHECK (latitude BETWEEN -90 AND 90)');

        DB::statement('ALTER TABLE work_locations ADD CONSTRAINT chk_wl_longitude
            CHECK (longitude BETWEEN -180 AND 180)');
    }

    public function down(): void
    {
        Schema::dropIfExists('work_locations');
    }
};
