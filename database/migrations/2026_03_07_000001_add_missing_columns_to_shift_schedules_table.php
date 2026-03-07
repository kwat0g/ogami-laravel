<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds columns declared in the ShiftSchedule model but missing from the
 * original create_shift_schedules_table migration:
 *   - description        (nullable text)
 *   - is_flexible        (boolean, default false)
 *   - grace_period_minutes (smallint, default 10)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('shift_schedules', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (! Schema::hasColumn('shift_schedules', 'is_flexible')) {
                $table->boolean('is_flexible')->default(false)->after('is_night_shift');
            }

            if (! Schema::hasColumn('shift_schedules', 'grace_period_minutes')) {
                $table->unsignedSmallInteger('grace_period_minutes')->default(10)->after('break_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_flexible', 'grace_period_minutes']);
        });
    }
};
