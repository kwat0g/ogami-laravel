<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RDAC — Role-Restricted Department Access Control
 *
 * Replaces the single `department_id` FK on `users` with a many-to-many
 * pivot. A user can now be assigned to multiple departments (e.g. an HR
 * Manager assigned to ALL departments) while one department remains the
 * "primary" for display purposes.
 *
 * The `users.department_id` column is kept as a denormalised primary dept
 * pointer for backward-compat with existing scopes; not dropped here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_department_access', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('department_id');
            $table->boolean('is_primary')->default(false)->comment('Denotes the home department shown in the UI');
            $table->timestamps();

            $table->unique(['user_id', 'department_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();

            $table->index(['user_id', 'is_primary']);
        });

        // Back-fill from the existing users.department_id column
        DB::statement(<<<'SQL'
            INSERT INTO user_department_access (user_id, department_id, is_primary, created_at, updated_at)
            SELECT id, department_id, true, NOW(), NOW()
            FROM   users
            WHERE  department_id IS NOT NULL
            ON CONFLICT (user_id, department_id) DO NOTHING
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_department_access');
    }
};
