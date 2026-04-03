<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_masters', function (Blueprint $table): void {
            $table->decimal('preferred_stock_level', 15, 4)->default(0)->after('reorder_qty');
            $table->decimal('min_batch_size', 15, 4)->default(0)->after('preferred_stock_level');
        });

        Schema::table('production_orders', function (Blueprint $table): void {
            $table->boolean('requires_release_approval')->default(false)->after('source_id');
            $table->foreignId('approved_for_release_by')->nullable()->after('requires_release_approval')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_for_release_at')->nullable()->after('approved_for_release_by');
            $table->text('release_approval_notes')->nullable()->after('approved_for_release_at');
        });

        DB::statement('ALTER TABLE item_masters ADD CONSTRAINT chk_item_masters_preferred_stock_level_nonneg CHECK (preferred_stock_level >= 0)');
        DB::statement('ALTER TABLE item_masters ADD CONSTRAINT chk_item_masters_min_batch_size_nonneg CHECK (min_batch_size >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE item_masters DROP CONSTRAINT IF EXISTS chk_item_masters_preferred_stock_level_nonneg');
        DB::statement('ALTER TABLE item_masters DROP CONSTRAINT IF EXISTS chk_item_masters_min_batch_size_nonneg');

        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropForeign(['approved_for_release_by']);
            $table->dropColumn([
                'requires_release_approval',
                'approved_for_release_by',
                'approved_for_release_at',
                'release_approval_notes',
            ]);
        });

        Schema::table('item_masters', function (Blueprint $table): void {
            $table->dropColumn(['preferred_stock_level', 'min_batch_size']);
        });
    }
};
