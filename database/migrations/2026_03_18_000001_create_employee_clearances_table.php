<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('department_code', 20); // IT, HR, FINANCE, DEPT, WAREHOUSE
            $table->string('item_description', 255);
            $table->string('status', 20)->default('pending'); // pending, in_progress, cleared, blocked
            $table->text('notes')->nullable();
            $table->foreignId('cleared_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['department_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_clearances');
    }
};
