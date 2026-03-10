<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a free-text remarks column to material_requisitions.
 *
 * Used by MaterialRequisitionService to store stock-override justifications
 * at submission time (PROD-002 / GAP-INV-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_requisitions', function (Blueprint $table): void {
            $table->text('remarks')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('material_requisitions', function (Blueprint $table): void {
            $table->dropColumn('remarks');
        });
    }
};
