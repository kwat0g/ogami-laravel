<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.4 — Mold cost amortization fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('mold_masters', 'cost_centavos')) {
            Schema::table('mold_masters', function (Blueprint $table): void {
                $table->unsignedBigInteger('cost_centavos')->default(0)->after('max_shots');
                $table->unsignedInteger('expected_total_shots')->nullable()->after('cost_centavos');
            });
        }
    }

    public function down(): void
    {
        Schema::table('mold_masters', function (Blueprint $table): void {
            $table->dropColumn(['cost_centavos', 'expected_total_shots']);
        });
    }
};
