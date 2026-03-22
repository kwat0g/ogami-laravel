<?php

declare(strict_types=1);

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
        Schema::table('material_requisitions', function (Blueprint $table): void {
            // Track if this MRQ has been converted to a PR
            $table->boolean('converted_to_pr')
                ->default(false)
                ->after('status');

            // Reference to the created PR (for tracking)
            $table->foreignId('converted_pr_id')
                ->nullable()
                ->after('converted_to_pr')
                ->constrained('purchase_requests')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_requisitions', function (Blueprint $table): void {
            $table->dropForeign(['converted_pr_id']);
            $table->dropColumn(['converted_to_pr', 'converted_pr_id']);
        });
    }
};
