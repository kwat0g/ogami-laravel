<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_masters', function (Blueprint $table) {
            $table->foreignId('preferred_vendor_id')
                ->nullable()
                ->after('is_active')
                ->constrained('vendors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('item_masters', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Domains\AP\Models\Vendor::class, 'preferred_vendor_id');
            $table->dropColumn('preferred_vendor_id');
        });
    }
};
