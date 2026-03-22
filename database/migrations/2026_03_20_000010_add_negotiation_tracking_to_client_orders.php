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
            // Track whose turn it is in negotiation
            $table->string('negotiation_turn', 10)->nullable()->after('negotiation_notes')
                ->comment('Whose turn to respond: sales or client');

            // Track negotiation round number
            $table->tinyInteger('negotiation_round')->default(0)->after('negotiation_turn')
                ->comment('Number of back-and-forth negotiation rounds');

            // Track last negotiation action
            $table->string('last_negotiation_by', 10)->nullable()->after('negotiation_round')
                ->comment('Who made the last negotiation action: sales or client');

            $table->timestamp('last_negotiation_at')->nullable()->after('last_negotiation_by')
                ->comment('When the last negotiation action occurred');

            // Store the last proposal made (JSON)
            $table->json('last_proposal')->nullable()->after('last_negotiation_at')
                ->comment('Last proposal details including who made it');
        });
    }

    public function down(): void
    {
        Schema::table('client_orders', function (Blueprint $table) {
            $table->dropColumn(['negotiation_turn', 'negotiation_round', 'last_negotiation_by', 'last_negotiation_at', 'last_proposal']);
        });
    }
};
