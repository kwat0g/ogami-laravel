<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update the check constraint to include 'delivery' transaction type
        DB::statement('ALTER TABLE stock_ledger DROP CONSTRAINT IF EXISTS chk_sl_txn_type');
        DB::statement("ALTER TABLE stock_ledger ADD CONSTRAINT chk_sl_txn_type CHECK (transaction_type IN ('goods_receipt','issue','transfer','adjustment','return','production_output','delivery'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stock_ledger DROP CONSTRAINT IF EXISTS chk_sl_txn_type');
        DB::statement("ALTER TABLE stock_ledger ADD CONSTRAINT chk_sl_txn_type CHECK (transaction_type IN ('goods_receipt','issue','transfer','adjustment','return','production_output'))");
    }
};
