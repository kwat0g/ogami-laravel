<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alter the default Laravel `users` table to add ERP-specific columns.
 *
 * The `users` table is the Sanctum auth principal. HR employee details are
 * stored in the `employees` table (Phase 1B). Users are linked to employees
 * via employees.user_id.
 *
 * Security rules (§14):
 *  - 5 failed attempts → locked for 15 minutes (locked_until)
 *  - Password must be changed every 90 days (password_changed_at)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('department_id')->nullable()->after('remember_token');
            $table->timestamp('last_login_at')->nullable()->after('department_id');
            $table->unsignedSmallInteger('failed_login_attempts')->default(0)->after('last_login_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->timestamp('password_changed_at')->nullable()->after('locked_until');
            $table->string('timezone', 50)->default('Asia/Manila')->after('password_changed_at');

            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
            $table->dropColumn([
                'department_id',
                'last_login_at',
                'failed_login_attempts',
                'locked_until',
                'password_changed_at',
                'timezone',
            ]);
        });
    }
};
