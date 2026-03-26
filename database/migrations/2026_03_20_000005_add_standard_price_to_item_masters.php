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
        Schema::table('item_masters', function (Blueprint $table): void {
            $table->unsignedBigInteger('standard_price_centavos')
                ->nullable()
                ->after('description')
                ->comment('Standard selling price in centavos for client portal');
        });

        // Add CHECK constraint for non-negative prices
        DB::statement('ALTER TABLE item_masters ADD CONSTRAINT chk_item_standard_price CHECK (standard_price_centavos IS NULL OR standard_price_centavos >= 0)');
    }

    public function down(): void
    {
        Schema::table('item_masters', function (Blueprint $table): void {
            $table->dropColumn('standard_price_centavos');
        });
    }
};
