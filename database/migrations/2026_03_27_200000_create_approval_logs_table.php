<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE approval_logs (
                id              BIGSERIAL       PRIMARY KEY,
                approvable_type VARCHAR(255)    NOT NULL,
                approvable_id   BIGINT          NOT NULL,
                stage           VARCHAR(60)     NOT NULL,
                action          VARCHAR(30)     NOT NULL,
                user_id         BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                remarks         TEXT,
                metadata        JSONB,
                created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
            )
        ");

        DB::statement("ALTER TABLE approval_logs ADD CONSTRAINT chk_al_action CHECK (action IN ('approved','rejected','returned','noted','checked','reviewed','processed'))");
        DB::statement("CREATE INDEX idx_approval_logs_morph ON approval_logs (approvable_type, approvable_id)");
        DB::statement("CREATE INDEX idx_approval_logs_user ON approval_logs (user_id)");
        DB::statement("CREATE INDEX idx_approval_logs_created ON approval_logs (created_at)");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS approval_logs");
    }
};
