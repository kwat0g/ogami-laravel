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
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('ulid', 26)->nullable()->after('id');
        });

        // Backfill existing rows with a generated ULID using PostgreSQL's gen_random_uuid()
        // Converted to text gives a UUID string; we use it as a surrogate ULID for existing data
        DB::statement("UPDATE customers SET ulid = REPLACE(gen_random_uuid()::text, '-', '') WHERE ulid IS NULL");

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('ulid', 26)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
