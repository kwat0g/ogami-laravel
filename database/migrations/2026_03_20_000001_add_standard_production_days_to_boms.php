<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_of_materials', function (Blueprint $table): void {
            $table->unsignedSmallInteger('standard_production_days')->default(1)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('bill_of_materials', function (Blueprint $table): void {
            $table->dropColumn('standard_production_days');
        });
    }
};
