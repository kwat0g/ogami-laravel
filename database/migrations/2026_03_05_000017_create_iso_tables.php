<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            -- ── Sequences ─────────────────────────────────────────────────────────
            CREATE SEQUENCE IF NOT EXISTS doc_code_seq START 1;
            CREATE SEQUENCE IF NOT EXISTS audit_ref_seq START 1;

            -- ── controlled_documents ──────────────────────────────────────────────
            CREATE TABLE controlled_documents (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)     NOT NULL UNIQUE,
                doc_code        VARCHAR(30)  NOT NULL UNIQUE,
                title           VARCHAR(300) NOT NULL,
                category        VARCHAR(100),
                document_type   VARCHAR(50)  NOT NULL DEFAULT 'procedure',
                owner_id        BIGINT       REFERENCES users(id) ON DELETE RESTRICT,
                current_version VARCHAR(20)  NOT NULL DEFAULT '1.0',
                status          VARCHAR(20)  NOT NULL DEFAULT 'draft',
                effective_date  DATE,
                review_date     DATE,
                is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
                created_by_id   BIGINT       REFERENCES users(id) ON DELETE RESTRICT,
                created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_doc_type   CHECK (document_type IN ('procedure','work_instruction','form','manual','policy','record')),
                CONSTRAINT chk_doc_status CHECK (status IN ('draft','under_review','approved','obsolete'))
            );

            CREATE OR REPLACE FUNCTION fn_set_doc_code()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                NEW.doc_code := 'DOC-' || LPAD(nextval('doc_code_seq')::TEXT, 5, '0');
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER trg_set_doc_code
                BEFORE INSERT ON controlled_documents
                FOR EACH ROW EXECUTE FUNCTION fn_set_doc_code();

            -- ── document_revisions ────────────────────────────────────────────────
            CREATE TABLE document_revisions (
                id                    BIGSERIAL PRIMARY KEY,
                ulid                  CHAR(26)     NOT NULL UNIQUE,
                controlled_document_id BIGINT      NOT NULL REFERENCES controlled_documents(id) ON DELETE CASCADE,
                version               VARCHAR(20)  NOT NULL,
                change_summary        TEXT,
                file_path             TEXT,
                revised_by_id         BIGINT       REFERENCES users(id) ON DELETE RESTRICT,
                approved_by_id        BIGINT       REFERENCES users(id) ON DELETE RESTRICT,
                approved_at           TIMESTAMPTZ,
                created_at            TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            );

            -- ── internal_audits ───────────────────────────────────────────────────
            CREATE TABLE internal_audits (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)     NOT NULL UNIQUE,
                audit_reference VARCHAR(30)  NOT NULL UNIQUE,
                audit_scope     TEXT         NOT NULL,
                standard        VARCHAR(100) NOT NULL DEFAULT 'ISO 9001:2015',
                lead_auditor_id BIGINT       REFERENCES users(id) ON DELETE RESTRICT,
                audit_date      DATE         NOT NULL,
                status          VARCHAR(20)  NOT NULL DEFAULT 'planned',
                summary         TEXT,
                closed_at       TIMESTAMPTZ,
                created_by_id   BIGINT       REFERENCES users(id) ON DELETE RESTRICT,
                created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_audit_status CHECK (status IN ('planned','in_progress','completed','closed'))
            );

            CREATE OR REPLACE FUNCTION fn_set_audit_reference()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                NEW.audit_reference := 'AUDIT-' || TO_CHAR(NOW(), 'YYYY-MM') || '-' || LPAD(nextval('audit_ref_seq')::TEXT, 5, '0');
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER trg_set_audit_reference
                BEFORE INSERT ON internal_audits
                FOR EACH ROW EXECUTE FUNCTION fn_set_audit_reference();

            -- ── audit_findings ────────────────────────────────────────────────────
            CREATE TABLE audit_findings (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)     NOT NULL UNIQUE,
                audit_id        BIGINT       NOT NULL REFERENCES internal_audits(id) ON DELETE CASCADE,
                finding_type    VARCHAR(20)  NOT NULL DEFAULT 'observation',
                clause_ref      VARCHAR(50),
                description     TEXT         NOT NULL,
                severity        VARCHAR(20)  NOT NULL DEFAULT 'minor',
                status          VARCHAR(20)  NOT NULL DEFAULT 'open',
                raised_by_id    BIGINT       REFERENCES users(id) ON DELETE RESTRICT,
                closed_at       TIMESTAMPTZ,
                created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_finding_type     CHECK (finding_type IN ('nonconformity','observation','opportunity')),
                CONSTRAINT chk_finding_severity CHECK (severity IN ('minor','major')),
                CONSTRAINT chk_finding_status   CHECK (status IN ('open','in_progress','closed','verified'))
            );

            -- ── improvement_actions ───────────────────────────────────────────────
            CREATE TABLE improvement_actions (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)     NOT NULL UNIQUE,
                finding_id      BIGINT       REFERENCES audit_findings(id) ON DELETE RESTRICT,
                title           VARCHAR(300) NOT NULL,
                description     TEXT,
                action_type     VARCHAR(20)  NOT NULL DEFAULT 'corrective',
                assigned_to_id  BIGINT       REFERENCES users(id) ON DELETE RESTRICT,
                due_date        DATE,
                completed_at    TIMESTAMPTZ,
                status          VARCHAR(20)  NOT NULL DEFAULT 'open',
                verified_by_id  BIGINT       REFERENCES users(id) ON DELETE RESTRICT,
                verified_at     TIMESTAMPTZ,
                created_by_id   BIGINT       REFERENCES users(id) ON DELETE RESTRICT,
                created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_ia_type   CHECK (action_type IN ('corrective','preventive','improvement')),
                CONSTRAINT chk_ia_status CHECK (status IN ('open','in_progress','completed','verified','cancelled'))
            );

            -- ── Indexes ───────────────────────────────────────────────────────────
            CREATE INDEX ON controlled_documents (status);
            CREATE INDEX ON controlled_documents (owner_id);
            CREATE INDEX ON document_revisions (controlled_document_id);
            CREATE INDEX ON internal_audits (status);
            CREATE INDEX ON internal_audits (audit_date);
            CREATE INDEX ON audit_findings (audit_id);
            CREATE INDEX ON audit_findings (status);
            CREATE INDEX ON improvement_actions (finding_id);
            CREATE INDEX ON improvement_actions (status);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS improvement_actions CASCADE;
            DROP TABLE IF EXISTS audit_findings CASCADE;
            DROP TABLE IF EXISTS internal_audits CASCADE;
            DROP TABLE IF EXISTS document_revisions CASCADE;
            DROP TABLE IF EXISTS controlled_documents CASCADE;
            DROP SEQUENCE IF EXISTS audit_ref_seq;
            DROP SEQUENCE IF EXISTS doc_code_seq;
        SQL);
    }
};
