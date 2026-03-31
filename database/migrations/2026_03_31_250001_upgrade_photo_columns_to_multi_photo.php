<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Upgrade single-photo columns to multi-photo (JSONB arrays, max 3).
 *
 * 1. delivery_receipts.pod_photo_path (VARCHAR) -> pod_photo_paths (JSONB)
 * 2. delivery_dispute_items.photo_url (TEXT) -> photo_urls (JSONB)
 *
 * Existing single values are migrated into JSON arrays for backward compatibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. delivery_receipts: pod_photo_path -> pod_photo_paths ──────────
        DB::statement("
            ALTER TABLE delivery_receipts
            ADD COLUMN IF NOT EXISTS pod_photo_paths JSONB DEFAULT '[]'::jsonb
        ");

        // Migrate existing single photo to array
        DB::statement("
            UPDATE delivery_receipts
            SET pod_photo_paths = jsonb_build_array(pod_photo_path)
            WHERE pod_photo_path IS NOT NULL AND pod_photo_path != ''
        ");

        // Drop old column
        DB::statement("
            ALTER TABLE delivery_receipts
            DROP COLUMN IF EXISTS pod_photo_path
        ");

        // ── 2. delivery_dispute_items: photo_url -> photo_urls ───────────────
        DB::statement("
            ALTER TABLE delivery_dispute_items
            ADD COLUMN IF NOT EXISTS photo_urls JSONB DEFAULT '[]'::jsonb
        ");

        // Migrate existing single photo to array
        DB::statement("
            UPDATE delivery_dispute_items
            SET photo_urls = jsonb_build_array(photo_url)
            WHERE photo_url IS NOT NULL AND photo_url != ''
        ");

        // Drop old column
        DB::statement("
            ALTER TABLE delivery_dispute_items
            DROP COLUMN IF EXISTS photo_url
        ");
    }

    public function down(): void
    {
        // ── Restore delivery_receipts.pod_photo_path ─────────────────────────
        DB::statement("
            ALTER TABLE delivery_receipts
            ADD COLUMN IF NOT EXISTS pod_photo_path VARCHAR(500)
        ");

        DB::statement("
            UPDATE delivery_receipts
            SET pod_photo_path = pod_photo_paths->>0
            WHERE pod_photo_paths IS NOT NULL AND jsonb_array_length(pod_photo_paths) > 0
        ");

        DB::statement("
            ALTER TABLE delivery_receipts
            DROP COLUMN IF EXISTS pod_photo_paths
        ");

        // ── Restore delivery_dispute_items.photo_url ─────────────────────────
        DB::statement("
            ALTER TABLE delivery_dispute_items
            ADD COLUMN IF NOT EXISTS photo_url TEXT
        ");

        DB::statement("
            UPDATE delivery_dispute_items
            SET photo_url = photo_urls->>0
            WHERE photo_urls IS NOT NULL AND jsonb_array_length(photo_urls) > 0
        ");

        DB::statement("
            ALTER TABLE delivery_dispute_items
            DROP COLUMN IF EXISTS photo_urls
        ");
    }
};
