<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE inspection_templates (
                id            BIGSERIAL PRIMARY KEY,
                ulid          CHAR(26)         NOT NULL UNIQUE,
                name          VARCHAR(200)     NOT NULL,
                stage         VARCHAR(20)      NOT NULL,          -- iqc | ipqc | oqc
                description   TEXT,
                is_active     BOOLEAN          NOT NULL DEFAULT TRUE,
                created_by_id BIGINT           REFERENCES users(id) ON DELETE SET NULL,
                created_at    TIMESTAMPTZ      NOT NULL DEFAULT NOW(),
                updated_at    TIMESTAMPTZ      NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_insp_template_stage CHECK (stage IN ('iqc','ipqc','oqc'))
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE inspection_template_items (
                id                      BIGSERIAL PRIMARY KEY,
                inspection_template_id  BIGINT NOT NULL REFERENCES inspection_templates(id) ON DELETE CASCADE,
                criterion               VARCHAR(300) NOT NULL,
                method                  VARCHAR(200),
                acceptable_range        VARCHAR(200),
                sort_order              SMALLINT NOT NULL DEFAULT 0,
                created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE inspections (
                id                      BIGSERIAL PRIMARY KEY,
                ulid                    CHAR(26)        NOT NULL UNIQUE,
                inspection_reference    VARCHAR(30)     NOT NULL,
                stage                   VARCHAR(20)     NOT NULL,        -- iqc | ipqc | oqc
                status                  VARCHAR(20)     NOT NULL DEFAULT 'open',  -- open | passed | failed | on_hold
                inspection_template_id  BIGINT          REFERENCES inspection_templates(id) ON DELETE SET NULL,
                -- Polymorphic source: goods_receipt_id OR production_order_id
                goods_receipt_id        BIGINT          REFERENCES goods_receipts(id) ON DELETE SET NULL,
                production_order_id     BIGINT          REFERENCES production_orders(id) ON DELETE SET NULL,
                item_master_id          BIGINT          REFERENCES item_masters(id) ON DELETE SET NULL,
                lot_batch_id            BIGINT          REFERENCES lot_batches(id) ON DELETE SET NULL,
                qty_inspected           NUMERIC(15,4)   NOT NULL DEFAULT 0,
                qty_passed              NUMERIC(15,4)   NOT NULL DEFAULT 0,
                qty_failed              NUMERIC(15,4)   NOT NULL DEFAULT 0,
                inspection_date         DATE            NOT NULL,
                inspector_id            BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                remarks                 TEXT,
                created_by_id           BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                created_at              TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                updated_at              TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_inspection_stage  CHECK (stage IN ('iqc','ipqc','oqc')),
                CONSTRAINT chk_inspection_status CHECK (status IN ('open','passed','failed','on_hold'))
            )
        SQL);

        // Auto-generate inspection reference: INSP-YYYY-MM-NNNNN
        DB::statement(<<<'SQL'
            CREATE SEQUENCE IF NOT EXISTS insp_ref_seq START 1
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_insp_reference() RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                NEW.inspection_reference := 'INSP-' || TO_CHAR(NOW(), 'YYYY-MM') || '-' || LPAD(NEXTVAL('insp_ref_seq')::TEXT, 5, '0');
                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_insp_reference
            BEFORE INSERT ON inspections
            FOR EACH ROW EXECUTE FUNCTION fn_insp_reference()
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE inspection_results (
                id                          BIGSERIAL PRIMARY KEY,
                inspection_id               BIGINT NOT NULL REFERENCES inspections(id) ON DELETE CASCADE,
                inspection_template_item_id BIGINT REFERENCES inspection_template_items(id) ON DELETE SET NULL,
                criterion                   VARCHAR(300) NOT NULL,
                actual_value                VARCHAR(200),
                is_conforming               BOOLEAN,
                remarks                     TEXT,
                created_at                  TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE non_conformance_reports (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)        NOT NULL UNIQUE,
                ncr_reference   VARCHAR(30)     NOT NULL,
                inspection_id   BIGINT          NOT NULL REFERENCES inspections(id) ON DELETE RESTRICT,
                title           VARCHAR(300)    NOT NULL,
                description     TEXT            NOT NULL,
                severity        VARCHAR(20)     NOT NULL DEFAULT 'minor',   -- minor | major | critical
                status          VARCHAR(20)     NOT NULL DEFAULT 'open',    -- open | under_review | capa_issued | closed | voided
                raised_by_id    BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                closed_at       TIMESTAMPTZ,
                closed_by_id    BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_ncr_severity CHECK (severity IN ('minor','major','critical')),
                CONSTRAINT chk_ncr_status   CHECK (status IN ('open','under_review','capa_issued','closed','voided'))
            )
        SQL);

        // Auto-generate NCR reference: NCR-YYYY-MM-NNNNN
        DB::statement(<<<'SQL'
            CREATE SEQUENCE IF NOT EXISTS ncr_ref_seq START 1
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_ncr_reference() RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                NEW.ncr_reference := 'NCR-' || TO_CHAR(NOW(), 'YYYY-MM') || '-' || LPAD(NEXTVAL('ncr_ref_seq')::TEXT, 5, '0');
                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_ncr_reference
            BEFORE INSERT ON non_conformance_reports
            FOR EACH ROW EXECUTE FUNCTION fn_ncr_reference()
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE capa_actions (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)        NOT NULL UNIQUE,
                ncr_id          BIGINT          NOT NULL REFERENCES non_conformance_reports(id) ON DELETE RESTRICT,
                type            VARCHAR(20)     NOT NULL DEFAULT 'corrective',   -- corrective | preventive
                description     TEXT            NOT NULL,
                due_date        DATE            NOT NULL,
                assigned_to_id  BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                status          VARCHAR(20)     NOT NULL DEFAULT 'open',  -- open | in_progress | completed | verified
                completed_at    TIMESTAMPTZ,
                verified_by_id  BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                verified_at     TIMESTAMPTZ,
                evidence_note   TEXT,
                created_by_id   BIGINT          REFERENCES users(id) ON DELETE SET NULL,
                created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_capa_type   CHECK (type IN ('corrective','preventive')),
                CONSTRAINT chk_capa_status CHECK (status IN ('open','in_progress','completed','verified'))
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS capa_actions CASCADE');
        DB::statement('DROP TABLE IF EXISTS non_conformance_reports CASCADE');
        DB::statement('DROP SEQUENCE IF EXISTS ncr_ref_seq');
        DB::statement('DROP TABLE IF EXISTS inspection_results CASCADE');
        DB::statement('DROP TABLE IF EXISTS inspections CASCADE');
        DB::statement('DROP SEQUENCE IF EXISTS insp_ref_seq');
        DB::statement('DROP TABLE IF EXISTS inspection_template_items CASCADE');
        DB::statement('DROP TABLE IF EXISTS inspection_templates CASCADE');
    }
};
