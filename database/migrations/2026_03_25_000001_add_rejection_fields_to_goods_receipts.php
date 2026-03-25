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
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('condition_notes');
            $table->foreignId('rejected_by_id')->nullable()->constrained('users')->nullOnDelete()->after('rejection_reason');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_id');
        });

        DB::statement('ALTER TABLE goods_receipts DROP CONSTRAINT IF EXISTS goods_receipts_status_check');
        DB::statement("ALTER TABLE goods_receipts ADD CONSTRAINT goods_receipts_status_check CHECK (status IN ('draft','confirmed','rejected'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE goods_receipts DROP CONSTRAINT IF EXISTS goods_receipts_status_check');
        DB::statement("ALTER TABLE goods_receipts ADD CONSTRAINT goods_receipts_status_check CHECK (status IN ('draft','confirmed'))");

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropForeign(['rejected_by_id']);
            $table->dropColumn(['rejection_reason', 'rejected_by_id', 'rejected_at']);
        });
    }
};
