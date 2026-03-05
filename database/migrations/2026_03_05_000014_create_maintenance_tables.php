<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE equipment (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)        NOT NULL UNIQUE,
                equipment_code  VARCHAR(30)     NOT NULL UNIQUE,
                name            VARCHAR(200)    NOT NULL,
                category        VARCHAR(100),
                manufacturer    VARCHAR(200),
                model_number    VARCHAR(100),
                serial_number   VARCHAR(100),
                location        VARCHAR(200),
                commissioned_on DATE,
                status          VARCHAR(20)     NOT NULL DEFAULT 'operational',  -- operational | under_maintenance | decommissioned
                is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
                created_by_id   BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_equipment_status CHECK (status IN ('operational','under_maintenance','decommissioned'))
            )
        SQL);

        // Auto-generate equipment code: EQ-000001
        DB::statement(<<<'SQL'
            CREATE SEQUENCE IF NOT EXISTS eq_code_seq START 1
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_equipment_code() RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.equipment_code IS NULL OR NEW.equipment_code = '' THEN
                    NEW.equipment_code := 'EQ-' || LPAD(NEXTVAL('eq_code_seq')::TEXT, 6, '0');
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_equipment_code
            BEFORE INSERT ON equipment
            FOR EACH ROW EXECUTE FUNCTION fn_equipment_code()
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE maintenance_work_orders (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)        NOT NULL UNIQUE,
                mwo_reference   VARCHAR(30)     NOT NULL,
                equipment_id    BIGINT          NOT NULL REFERENCES equipment(id) ON DELETE RESTRICT,
                type            VARCHAR(20)     NOT NULL DEFAULT 'corrective',  -- corrective | preventive
                priority        VARCHAR(10)     NOT NULL DEFAULT 'normal',      -- low | normal | high | critical
                status          VARCHAR(20)     NOT NULL DEFAULT 'open',        -- open | in_progress | completed | cancelled
                title           VARCHAR(300)    NOT NULL,
                description     TEXT,
                reported_by_id  BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                assigned_to_id  BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                scheduled_date  DATE,
                completed_at    TIMESTAMPTZ,
                completion_notes TEXT,
                created_by_id   BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_mwo_type     CHECK (type IN ('corrective','preventive')),
                CONSTRAINT chk_mwo_priority CHECK (priority IN ('low','normal','high','critical')),
                CONSTRAINT chk_mwo_status   CHECK (status IN ('open','in_progress','completed','cancelled'))
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE SEQUENCE IF NOT EXISTS mwo_ref_seq START 1
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_mwo_reference() RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                NEW.mwo_reference := 'MWO-' || TO_CHAR(NOW(), 'YYYY-MM') || '-' || LPAD(NEXTVAL('mwo_ref_seq')::TEXT, 5, '0');
                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_mwo_reference
            BEFORE INSERT ON maintenance_work_orders
            FOR EACH ROW EXECUTE FUNCTION fn_mwo_reference()
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE pm_schedules (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)        NOT NULL UNIQUE,
                equipment_id    BIGINT          NOT NULL REFERENCES equipment(id) ON DELETE CASCADE,
                task_name       VARCHAR(200)    NOT NULL,
                frequency_days  INT             NOT NULL,
                last_done_on    DATE,
                next_due_on     DATE GENERATED ALWAYS AS (
                    CASE WHEN last_done_on IS NOT NULL THEN last_done_on + frequency_days
                    ELSE NULL END
                ) STORED,
                is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
                created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS pm_schedules CASCADE');
        DB::statement('DROP TABLE IF EXISTS maintenance_work_orders CASCADE');
        DB::statement('DROP SEQUENCE IF EXISTS mwo_ref_seq');
        DB::statement('DROP TABLE IF EXISTS equipment CASCADE');
        DB::statement('DROP SEQUENCE IF EXISTS eq_code_seq');
    }
};
