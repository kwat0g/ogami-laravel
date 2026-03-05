<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE mold_masters (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)        NOT NULL UNIQUE,
                mold_code       VARCHAR(30)     NOT NULL UNIQUE,
                name            VARCHAR(200)    NOT NULL,
                description     TEXT,
                cavity_count    SMALLINT        NOT NULL DEFAULT 1,
                material        VARCHAR(100),
                location        VARCHAR(200),
                max_shots       BIGINT,
                current_shots   BIGINT          NOT NULL DEFAULT 0,
                last_maintenance_at TIMESTAMPTZ,
                status          VARCHAR(20)     NOT NULL DEFAULT 'active', -- active | under_maintenance | retired
                is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
                created_by_id   BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_mold_status CHECK (status IN ('active','under_maintenance','retired'))
            )
        SQL);

        // Auto-generate mold code: MOLD-000001
        DB::statement(<<<'SQL'
            CREATE SEQUENCE IF NOT EXISTS mold_code_seq START 1
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_mold_code() RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.mold_code IS NULL OR NEW.mold_code = '' THEN
                    NEW.mold_code := 'MOLD-' || LPAD(NEXTVAL('mold_code_seq')::TEXT, 6, '0');
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_mold_code
            BEFORE INSERT ON mold_masters
            FOR EACH ROW EXECUTE FUNCTION fn_mold_code()
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE mold_shot_logs (
                id              BIGSERIAL PRIMARY KEY,
                mold_id         BIGINT          NOT NULL REFERENCES mold_masters(id) ON DELETE CASCADE,
                production_order_id BIGINT      REFERENCES production_orders(id) ON DELETE SET NULL,
                shot_count      BIGINT          NOT NULL,
                operator_id     BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                log_date        DATE            NOT NULL DEFAULT CURRENT_DATE,
                remarks         TEXT,
                created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
            )
        SQL);

        // Trigger: accumulate shots into mold_masters.current_shots
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_update_mold_shots() RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                UPDATE mold_masters
                SET current_shots = current_shots + NEW.shot_count,
                    updated_at    = NOW()
                WHERE id = NEW.mold_id;
                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_update_mold_shots
            AFTER INSERT ON mold_shot_logs
            FOR EACH ROW EXECUTE FUNCTION fn_update_mold_shots()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS mold_shot_logs CASCADE');
        DB::statement('DROP TABLE IF EXISTS mold_masters CASCADE');
        DB::statement('DROP SEQUENCE IF EXISTS mold_code_seq');
    }
};
