<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_orders', function (Blueprint $table) {
            // Add last_negotiation tracking fields if they don't exist
            if (! Schema::hasColumn('client_orders', 'last_negotiation_by')) {
                $table->string('last_negotiation_by', 10)->nullable()->after('negotiation_round')
                    ->comment('Who made the last negotiation action: sales or client');
            }

            if (! Schema::hasColumn('client_orders', 'last_negotiation_at')) {
                $table->timestamp('last_negotiation_at')->nullable()->after('last_negotiation_by')
                    ->comment('When the last negotiation action occurred');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_orders', function (Blueprint $table) {
            $table->dropColumn(['last_negotiation_by', 'last_negotiation_at']);
        });
    }
};
