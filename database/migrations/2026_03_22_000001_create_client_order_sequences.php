<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // client_order_seq: used for CO-YYYY-NNNNN order references
        DB::statement('CREATE SEQUENCE IF NOT EXISTS client_order_seq START 1 INCREMENT 1');

        // cds_reference_seq: used for CDS-YYYY-NNNNN combined delivery schedule references
        // Previously shared client_order_seq — now separate to prevent reference number gaps
        DB::statement('CREATE SEQUENCE IF NOT EXISTS cds_reference_seq START 1 INCREMENT 1');
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS client_order_seq');
        DB::statement('DROP SEQUENCE IF EXISTS cds_reference_seq');
    }
};
