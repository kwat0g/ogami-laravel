<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.2 — Work Centers and Routing for production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_centers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('hourly_rate_centavos')->default(0);
            $table->unsignedBigInteger('overhead_rate_centavos')->default(0);
            $table->unsignedSmallInteger('capacity_hours_per_day')->default(8);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('routings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('bom_id')->constrained('bill_of_materials')->cascadeOnDelete();
            $table->foreignId('work_center_id')->constrained('work_centers');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('operation_name');
            $table->text('description')->nullable();
            $table->decimal('setup_time_hours', 8, 2)->default(0);
            $table->decimal('run_time_hours_per_unit', 8, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['bom_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routings');
        Schema::dropIfExists('work_centers');
    }
};
