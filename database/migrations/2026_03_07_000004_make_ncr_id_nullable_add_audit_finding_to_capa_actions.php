<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ISO-QC-001: Allow CAPA actions to be initiated directly from ISO Audit Findings
 * without requiring a Non-Conformance Report (NCR).
 *   1. Makes ncr_id nullable on capa_actions.
 *   2. Adds a nullable audit_finding_id FK for ISO-triggered CAPAs.
 *   3. Adds a CHECK so every CAPA still references either NCR or Audit Finding.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE capa_actions
                ALTER COLUMN ncr_id DROP NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE capa_actions
                ADD COLUMN audit_finding_id BIGINT
                    REFERENCES audit_findings(id) ON DELETE SET NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE capa_actions
                ADD CONSTRAINT chk_capa_has_source
                    CHECK (ncr_id IS NOT NULL OR audit_finding_id IS NOT NULL)
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE capa_actions
                DROP CONSTRAINT IF EXISTS chk_capa_has_source
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE capa_actions
                DROP COLUMN IF EXISTS audit_finding_id
        SQL);

        // Remove rows where ncr_id is null (came from audit findings) before
        // restoring the NOT NULL constraint — otherwise rollback will crash.
        DB::statement(<<<'SQL'
            DELETE FROM capa_actions WHERE ncr_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE capa_actions
                ALTER COLUMN ncr_id SET NOT NULL
        SQL);
    }
};
