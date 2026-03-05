<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            -- ── vehicles ─────────────────────────────────────────────────────────
            CREATE TABLE vehicles (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)        NOT NULL UNIQUE,
                code            VARCHAR(20)     NOT NULL UNIQUE,
                name            VARCHAR(100)    NOT NULL,
                type            VARCHAR(20)     NOT NULL DEFAULT 'truck',
                make_model      VARCHAR(100),
                plate_number    VARCHAR(30)     NOT NULL UNIQUE,
                status          VARCHAR(20)     NOT NULL DEFAULT 'active',
                notes           TEXT,
                created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_vehicle_type   CHECK (type   IN ('truck','van','motorcycle','other')),
                CONSTRAINT chk_vehicle_status CHECK (status IN ('active','inactive','maintenance'))
            );

            -- ── Add vehicle_id to delivery_receipts ──────────────────────────────
            ALTER TABLE delivery_receipts
                ADD COLUMN IF NOT EXISTS vehicle_id BIGINT REFERENCES vehicles(id) ON DELETE RESTRICT,
                ADD COLUMN IF NOT EXISTS driver_name VARCHAR(100);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE delivery_receipts
                DROP COLUMN IF EXISTS vehicle_id,
                DROP COLUMN IF EXISTS driver_name;

            DROP TABLE IF EXISTS vehicles;
        SQL);
    }
};
