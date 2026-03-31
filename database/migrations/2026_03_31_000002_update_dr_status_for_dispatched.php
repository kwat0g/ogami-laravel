<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE delivery_receipts DROP CONSTRAINT IF EXISTS chk_dr_status');
        DB::statement("ALTER TABLE delivery_receipts ADD CONSTRAINT chk_dr_status CHECK (status IN ('draft','confirmed','dispatched','partially_delivered','delivered','cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE delivery_receipts DROP CONSTRAINT IF EXISTS chk_dr_status');
        DB::statement("ALTER TABLE delivery_receipts ADD CONSTRAINT chk_dr_status CHECK (status IN ('draft','confirmed','delivered','cancelled'))");
    }
};
