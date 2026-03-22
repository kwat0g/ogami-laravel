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
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->foreignId('material_requisition_id')
                ->nullable()
                ->after('department_id')
                ->constrained('material_requisitions')
                ->nullOnDelete();

            // Add index for efficient lookups
            $table->index('material_requisition_id', 'idx_pr_source_mrq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropIndex('idx_pr_source_mrq');
            $table->dropForeign(['material_requisition_id']);
            $table->dropColumn('material_requisition_id');
        });
    }
};
