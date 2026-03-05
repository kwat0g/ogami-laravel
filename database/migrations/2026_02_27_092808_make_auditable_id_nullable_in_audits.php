<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            // auditable_id must be nullable to support failed login attempts for
            // unknown emails where there is no associated User record.
            $table->unsignedBigInteger('auditable_id')->nullable()->change();
            $table->string('auditable_type')->nullable()->change();
            $table->bigInteger('user_id')->nullable()->change();
            $table->string('user_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->unsignedBigInteger('auditable_id')->nullable(false)->change();
        });
    }
};
