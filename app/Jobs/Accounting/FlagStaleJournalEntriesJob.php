<?php

declare(strict_types=1);

namespace App\Jobs\Accounting;

use App\Domains\Accounting\Models\JournalEntry;
use App\Models\User;
use App\Notifications\JournalEntryStaleNotification;
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
        $stalledEntries = JournalEntry::where('status', 'draft')
            ->where('updated_at', '<=', $staleThreshold)
            ->get();

        $stalledCount = $stalledEntries->count();

        if ($stalledCount > 0) {
            // Update status to stale
            JournalEntry::whereIn('id', $stalledEntries->pluck('id'))
                ->update(['status' => 'stale', 'updated_at' => $today]);

            Log::info("[FlagStaleJournalEntriesJob] Flagged {$stalledCount} draft JE(s) as stale (idle ≥ {$staleDays} days).");

            // Notify drafters (group by creator to avoid spam)
            $entriesByCreator = $stalledEntries->groupBy('created_by');
            foreach ($entriesByCreator as $creatorId => $entries) {
                $creator = User::find($creatorId);
                if ($creator) {
                    foreach ($entries as $entry) {
                        $creator->notify(new JournalEntryStaleNotification($entry, $staleDays));
                    }
                }
            }

            Log::info("[FlagStaleJournalEntriesJob] Sent stale notifications to {$entriesByCreator->count()} drafter(s).");
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
