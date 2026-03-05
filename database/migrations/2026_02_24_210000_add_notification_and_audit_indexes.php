<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supplemental performance indexes — Phase 1E Sprint 18
 *
 * 1. notifications: The default morphs() index on (notifiable_type, notifiable_id)
 *    cannot support the unread-count query "WHERE notifiable_id = ? AND notifiable_type = ?
 *    AND read_at IS NULL" efficiently. A partial index that covers only unread rows
 *    is smaller and eliminates the read_at NULL check entirely.
 *
 * 2. audits: Compliance console queries filter by (user_id, date range), e.g.
 *    "show all actions by user X this month". The existing (user_id, user_type) index
 *    does not include created_at, forcing a filter + sort scan. Adding (user_id, created_at)
 *    enables index-only scans for this common access pattern.
 *
 * 3. audits: Entity audit history queries filter by (auditable_type, auditable_id, created_at)
 *    to show chronological history for a specific record. The existing morphs index on
 *    (auditable_id, auditable_type) helps row lookup but not ORDER BY created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. notifications — partial index for unread count (NotificationController::unreadCount)
        //    SELECT COUNT(*) FROM notifications
        //    WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL
        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_notifications_unread '
            .'ON notifications (notifiable_type, notifiable_id) '
            .'WHERE read_at IS NULL',
        );

        // 2. audits — user activity timeline used by compliance console
        //    WHERE user_id = ? AND user_type = ? AND created_at BETWEEN ? AND ?
        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_audits_user_created '
            .'ON audits (user_id, created_at DESC) '
            .'WHERE user_id IS NOT NULL',
        );

        // 3. audits — entity audit history ordered by time
        //    WHERE auditable_type = ? AND auditable_id = ? ORDER BY created_at DESC
        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_audits_auditable_created '
            .'ON audits (auditable_type, auditable_id, created_at DESC)',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_notifications_unread');
        DB::statement('DROP INDEX IF EXISTS idx_audits_user_created');
        DB::statement('DROP INDEX IF EXISTS idx_audits_auditable_created');
    }
};
