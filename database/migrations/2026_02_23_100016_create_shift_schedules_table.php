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
        Schema::create('shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();             // e.g. SHIFT-DAY
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(60);
            // Bitmask of ISO weekdays: 1=Mon … 7=Sun
            // Stored as comma-separated string for readability: "1,2,3,4,5"
            $table->string('work_days', 20)->default('1,2,3,4,5');
            $table->boolean('crosses_midnight')->default(false);
            $table->boolean('is_night_shift')->default(false)
                ->comment('Computed by app: true when start_time >= 22:00 or end_time <= 06:00');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE shift_schedules ADD CONSTRAINT chk_shift_break
            CHECK (break_minutes BETWEEN 0 AND 120)');
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_schedules');
    }
};
