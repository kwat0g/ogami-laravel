<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_orders', function (Blueprint $table): void {
            // Add ulid column after id
            $table->ulid('ulid')->unique()->nullable()->after('id');
        });

        // Generate ULIDs for existing records
        DB::table('client_orders')->get()->each(function ($order): void {
            DB::table('client_orders')
                ->where('id', $order->id)
                ->update(['ulid' => (string) Str::ulid()]);
        });

        // Make ulid non-nullable after populating (unique already set above)
        Schema::table('client_orders', function (Blueprint $table): void {
            $table->ulid('ulid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('client_orders', function (Blueprint $table): void {
            $table->dropColumn('ulid');
        });
    }
};
