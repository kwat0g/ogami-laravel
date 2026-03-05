<?php

declare(strict_types=1);

namespace App\Jobs\Accounting;

use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * JE-010: Daily job to flag stale draft journal entries.
 *
 * Two passes:
 *  1. Draft → STALE  after `accounting.stale_draft_days`  days of inactivity (default 30).
 *  2. Stale → CANCELLED after `accounting.je_cancel_days` days of inactivity (default 60).
 *
 * "Inactivity" = updated_at has not changed for the threshold period.
 *
 * Both thresholds are read from system_settings (zero hardcoding, S3).
 * A notification is sent to the drafter on flagging (TODO: notification class in Sprint 17).
 */
final class FlagStaleJournalEntriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        [$staleDays, $cancelDays] = $this->loadThresholds();

        $staleThreshold = now()->subDays($staleDays);
        $cancelThreshold = now()->subDays($cancelDays);

        $today = now()->toDateTimeString();

        // ── Pass 1: draft → stale ────────────────────────────────────────────
        $stalledCount = JournalEntry::where('status', 'draft')
            ->where('updated_at', '<=', $staleThreshold)
            ->update(['status' => 'stale', 'updated_at' => $today]);

        if ($stalledCount > 0) {
            Log::info("[FlagStaleJournalEntriesJob] Flagged {$stalledCount} draft JE(s) as stale (idle ≥ {$staleDays} days).");
            // TODO Sprint 17: dispatch notification to drafters
        }

        // ── Pass 2: stale → cancelled ────────────────────────────────────────
        $cancelledIds = JournalEntry::where('status', 'stale')
            ->where('updated_at', '<=', $cancelThreshold)
            ->pluck('id');

        if ($cancelledIds->isNotEmpty()) {
            JournalEntry::whereIn('id', $cancelledIds)
                ->update(['status' => 'cancelled', 'updated_at' => $today]);

            Log::info("[FlagStaleJournalEntriesJob] Auto-cancelled {$cancelledIds->count()} stale JE(s) (idle ≥ {$cancelDays} days).", [
                'cancelled_ids' => $cancelledIds->toArray(),
            ]);
        }
    }

    /**
     * Load stale/cancel thresholds from system_settings.
     * Falls back to roadmap defaults (30 / 60 days) if keys are missing.
     *
     * @return array{0: int, 1: int}
     */
    private function loadThresholds(): array
    {
        $rows = DB::table('system_settings')
            ->whereIn('key', ['accounting.stale_draft_days', 'accounting.je_cancel_days'])
            ->pluck('value', 'key');

        $staleDays = isset($rows['accounting.stale_draft_days'])
            ? (int) json_decode($rows['accounting.stale_draft_days'], true)
            : 30;

        $cancelDays = isset($rows['accounting.je_cancel_days'])
            ? (int) json_decode($rows['accounting.je_cancel_days'], true)
            : 60;

        return [$staleDays, $cancelDays];
    }
}
