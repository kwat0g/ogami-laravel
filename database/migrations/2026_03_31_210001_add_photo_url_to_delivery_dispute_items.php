<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_dispute_items', function (Blueprint $table): void {
            $table->text('photo_url')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_dispute_items', function (Blueprint $table): void {
            $table->dropColumn('photo_url');
        });
    }
};
