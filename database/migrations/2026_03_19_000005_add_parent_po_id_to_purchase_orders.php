<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('parent_po_id')->nullable()->after('purchase_request_id')
                ->constrained('purchase_orders')->nullOnDelete();
            $table->string('po_type', 20)->default('original')->after('status');
        });

        // Add constraint for po_type
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT chk_po_type CHECK (po_type IN ('original','split'))");
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropForeign(['parent_po_id']);
            $table->dropColumn('parent_po_id');
            $table->dropColumn('po_type');
        });
    }
};
