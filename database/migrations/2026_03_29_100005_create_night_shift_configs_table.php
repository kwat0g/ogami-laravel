<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Night shift configuration — defines the night differential window
 * and rate. Effective-date based so changes can be scheduled.
 *
 * Philippine labor law default: 10 PM – 6 AM, 10% premium.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('night_shift_configs', function (Blueprint $table) {
            $table->id();
            $table->time('night_start_time')->default('22:00:00');
            $table->time('night_end_time')->default('06:00:00');
            $table->unsignedSmallInteger('differential_rate_bps')->default(1000)
                ->comment('Rate in basis points: 1000 = 10%');
            $table->date('effective_date')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('night_shift_configs');
    }
};
