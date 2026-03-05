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
            CREATE SEQUENCE IF NOT EXISTS dr_ref_seq START 1;

            -- ── delivery_receipts ─────────────────────────────────────────────────
            CREATE TABLE delivery_receipts (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)         NOT NULL UNIQUE,
                dr_reference    VARCHAR(30)      NOT NULL UNIQUE,
                vendor_id       BIGINT           REFERENCES vendors(id) ON DELETE RESTRICT,
                customer_id     BIGINT           REFERENCES customers(id) ON DELETE RESTRICT,
                direction       VARCHAR(10)      NOT NULL DEFAULT 'inbound',
                status          VARCHAR(20)      NOT NULL DEFAULT 'draft',
                receipt_date    DATE             NOT NULL,
                remarks         TEXT,
                received_by_id  BIGINT           REFERENCES users(id) ON DELETE RESTRICT,
                created_by_id   BIGINT           REFERENCES users(id) ON DELETE RESTRICT,
                created_at      TIMESTAMPTZ      NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ      NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_dr_direction CHECK (direction IN ('inbound','outbound')),
                CONSTRAINT chk_dr_status    CHECK (status IN ('draft','confirmed','cancelled'))
            );

            -- DR reference trigger  DR-YYYY-MM-NNNNN
            CREATE OR REPLACE FUNCTION fn_set_dr_reference()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                NEW.dr_reference := 'DR-' || TO_CHAR(NOW(), 'YYYY-MM') || '-' || LPAD(nextval('dr_ref_seq')::TEXT, 5, '0');
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER trg_set_dr_reference
                BEFORE INSERT ON delivery_receipts
                FOR EACH ROW EXECUTE FUNCTION fn_set_dr_reference();

            -- ── delivery_receipt_items ────────────────────────────────────────────
            CREATE TABLE delivery_receipt_items (
                id                  BIGSERIAL PRIMARY KEY,
                delivery_receipt_id BIGINT NOT NULL REFERENCES delivery_receipts(id) ON DELETE CASCADE,
                item_master_id      BIGINT NOT NULL REFERENCES item_masters(id) ON DELETE RESTRICT,
                quantity_expected   NUMERIC(14,4) NOT NULL DEFAULT 0,
                quantity_received   NUMERIC(14,4) NOT NULL DEFAULT 0,
                unit_of_measure     VARCHAR(30),
                lot_batch_number    VARCHAR(100),
                remarks             TEXT,
                created_at          TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_dri_qty CHECK (quantity_received >= 0)
            );

            -- ── shipments ─────────────────────────────────────────────────────────
            CREATE SEQUENCE IF NOT EXISTS ship_ref_seq START 1;

            CREATE TABLE shipments (
                id                  BIGSERIAL PRIMARY KEY,
                ulid                CHAR(26)      NOT NULL UNIQUE,
                shipment_reference  VARCHAR(30)   NOT NULL UNIQUE,
                delivery_receipt_id BIGINT        REFERENCES delivery_receipts(id) ON DELETE RESTRICT,
                carrier             VARCHAR(200),
                tracking_number     VARCHAR(200),
                shipped_at          TIMESTAMPTZ,
                estimated_arrival   DATE,
                actual_arrival      DATE,
                status              VARCHAR(20)   NOT NULL DEFAULT 'pending',
                notes               TEXT,
                created_by_id       BIGINT        REFERENCES users(id) ON DELETE RESTRICT,
                created_at          TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
                updated_at          TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_ship_status CHECK (status IN ('pending','in_transit','delivered','returned'))
            );

            CREATE OR REPLACE FUNCTION fn_set_ship_reference()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                NEW.shipment_reference := 'SHIP-' || TO_CHAR(NOW(), 'YYYY-MM') || '-' || LPAD(nextval('ship_ref_seq')::TEXT, 5, '0');
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER trg_set_ship_reference
                BEFORE INSERT ON shipments
                FOR EACH ROW EXECUTE FUNCTION fn_set_ship_reference();

            -- ── impex_documents ───────────────────────────────────────────────────
            CREATE TABLE impex_documents (
                id              BIGSERIAL PRIMARY KEY,
                ulid            CHAR(26)      NOT NULL UNIQUE,
                shipment_id     BIGINT        NOT NULL REFERENCES shipments(id) ON DELETE RESTRICT,
                document_type   VARCHAR(50)   NOT NULL,
                document_number VARCHAR(100),
                issued_date     DATE,
                expiry_date     DATE,
                file_path       TEXT,
                notes           TEXT,
                created_by_id   BIGINT        REFERENCES users(id) ON DELETE RESTRICT,
                created_at      TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ   NOT NULL DEFAULT NOW()
            );

            -- ── Indexes ───────────────────────────────────────────────────────────
            CREATE INDEX ON delivery_receipts (vendor_id);
            CREATE INDEX ON delivery_receipts (customer_id);
            CREATE INDEX ON delivery_receipts (status);
            CREATE INDEX ON delivery_receipts (receipt_date);
            CREATE INDEX ON delivery_receipt_items (delivery_receipt_id);
            CREATE INDEX ON delivery_receipt_items (item_master_id);
            CREATE INDEX ON shipments (delivery_receipt_id);
            CREATE INDEX ON shipments (status);
            CREATE INDEX ON impex_documents (shipment_id);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS impex_documents CASCADE;
            DROP TABLE IF EXISTS shipments CASCADE;
            DROP TABLE IF EXISTS delivery_receipt_items CASCADE;
            DROP TABLE IF EXISTS delivery_receipts CASCADE;
            DROP SEQUENCE IF EXISTS ship_ref_seq;
            DROP SEQUENCE IF EXISTS dr_ref_seq;
        SQL);
    }
};
