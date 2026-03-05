<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Salary grades define pay range bands per level.
 * Linked to positions and employees for compensation management.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_grades', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();           // e.g. SG-01
            $table->string('name', 100);                    // e.g. Grade 1 — Entry Level
            $table->unsignedSmallInteger('level');          // 1–20
            $table->unsignedBigInteger('min_monthly_rate'); // centavos
            $table->unsignedBigInteger('max_monthly_rate'); // centavos
            $table->string('employment_type', 30)
                ->default('regular')
                ->comment('regular|contractual|project_based|casual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Check constraints added below via DB::statement()
        });

        DB::statement('ALTER TABLE salary_grades ADD CONSTRAINT chk_sg_rate_range CHECK (max_monthly_rate >= min_monthly_rate)');
        DB::statement('ALTER TABLE salary_grades ADD CONSTRAINT chk_sg_level CHECK (level BETWEEN 1 AND 20)');
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_grades');
    }
};
