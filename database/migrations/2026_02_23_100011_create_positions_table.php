<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Positions — job titles assigned to employees.
 *
 * Soft delete: positions cannot be hard-deleted when employees are assigned.
 * EMP-006: Assigning an archived (soft-deleted) position is blocked at service layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('title', 150);
            $table->unsignedBigInteger('department_id');
            $table->string('pay_grade', 20)->nullable()->comment('Pay grade or salary band, e.g. SG-15');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
