<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEntryLine;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\SodViolationException;
use App\Shared\Exceptions\UnbalancedJournalEntryException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Journal Entry Service — full JE lifecycle with JE-001 through JE-010 enforcement.
 *
 * JE-001: balanced double-entry (service + DB trigger).
 * JE-002: minimum 2 lines (FormRequest).
 * JE-003: no zero-value lines (FormRequest + DB CHECK).
 * JE-004: open fiscal period (OpenFiscalPeriodRule + service).
 * JE-005: no future period unless setting allows (NotFuturePeriodRule).
 * JE-006: posted JEs immutable (DB trigger + Policy).
 * JE-007: only one reversal per JE.
 * JE-008: auto-posted JEs cannot be manually edited (Policy).
 * JE-009: JE number auto-generated on posting.
 * JE-010: stale draft auto-flagging (FlagStaleJournalEntriesJob).
 */
final class JournalEntryService implements ServiceContract
{
    public function __construct(
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {}

    // ── Create (draft) ───────────────────────────────────────────────────────

    /**
     * Create a new DRAFT journal entry with lines.
     * source_type defaults to 'manual'; override to 'payroll'|'ap'|'ar' for auto-posts.
     *
     * @param  array{date: string, description: string, lines: array, source_type?: string, source_id?: int}  $data
     */
    public function create(array $data): JournalEntry
    {
        $lines = $data['lines'];

        // JE-001 service-layer balance check
        $this->assertBalanced($lines);

        $fiscalPeriod = $this->fiscalPeriodService->resolveForDateOrFail(
            \Illuminate\Support\Carbon::parse($data['date'])
        );

        return DB::transaction(function () use ($data, $fiscalPeriod) {
            $je = JournalEntry::create([
                'date' => $data['date'],
                'description' => $data['description'],
                'source_type' => $data['source_type'] ?? 'manual',
                'source_id' => $data['source_id'] ?? null,
                'status' => 'draft',
                'fiscal_period_id' => $fiscalPeriod->id,
                'created_by' => auth()->id(),
                'je_number' => null,
            ]);

            foreach ($data['lines'] as $line) {
                $je->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? null,
                    'credit' => $line['credit'] ?? null,
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $je->load('lines');
        });
    }

    // ── Submit ───────────────────────────────────────────────────────────────

    public function submit(JournalEntry $je): JournalEntry
    {
        $this->assertDraftOrStale($je, 'submitted');

        $je->update([
            'status' => 'submitted',
            'submitted_by' => auth()->id(),
        ]);

        return $je->fresh();
    }

    // ── Post ─────────────────────────────────────────────────────────────────

    /**
     * Post a submitted JE. Enforces SoD (JE-010) and assigns JE number (JE-009).
     */
    public function post(JournalEntry $je): JournalEntry
    {
        if (! in_array($je->status, ['submitted', 'draft'], true)) {
            throw new DomainException(
                message: "Only submitted or draft journal entries can be posted. Current status: '{$je->status}'.",
                errorCode: 'INVALID_JE_STATUS_FOR_POSTING',
                httpStatus: 409,
                context: ['je_id' => $je->id, 'status' => $je->status],
            );
        }

        // SoD check (JE-010): poster cannot be the drafter (super_admin bypasses)
        if ($je->source_type === 'manual' && ! auth()->user()?->hasRole('super_admin') && auth()->id() === $je->created_by) {
            throw new SodViolationException(
                processName: 'Journal Entry',
                conflictingAction: 'post',
            );
        }

        // Re-validate balance before posting (belt + suspenders)
        $lines = $je->lines->map(fn ($l) => ['debit' => $l->debit, 'credit' => $l->credit])->toArray();
        $this->assertBalanced($lines);

        $jeNumber = $this->generateJeNumber(
            $je->date instanceof \Illuminate\Support\Carbon
                ? $je->date->toDateString()
                : (string) $je->date,
        );

        return DB::transaction(function () use ($je, $jeNumber) {
            $je->update([
                'status' => 'posted',
                'je_number' => $jeNumber,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            return $je->fresh(['lines.account', 'fiscalPeriod']);
        });
    }

    // ── Reverse ──────────────────────────────────────────────────────────────

    /**
     * Create a reversing JE (JE-007).
     * The original JE may only be reversed once.
     * Returns the new reversing JournalEntry (as a posted entry).
     */
    public function reverse(JournalEntry $original, string $description): JournalEntry
    {
        if (! $original->isPosted()) {
            throw new DomainException(
                message: "Only posted journal entries can be reversed. Current status: '{$original->status}'.",
                errorCode: 'JE_NOT_POSTED',
                httpStatus: 422,
                context: ['je_id' => $original->id],
            );
        }

        // JE-007: one reversal only
        if ($original->hasBeenReversed()) {
            throw new DomainException(
                message: "Journal entry {$original->je_number} has already been reversed. It can only be reversed once. (JE-007)",
                errorCode: 'JE_ALREADY_REVERSED',
                httpStatus: 422,
                context: ['je_id' => $original->id, 'je_number' => $original->je_number],
            );
        }

        // Build mirrored lines (swap debit ↔ credit)
        $reversedLines = $original->lines->map(fn (JournalEntryLine $l) => [
            'account_id' => $l->account_id,
            'debit' => $l->credit,    // swap
            'credit' => $l->debit,     // swap
            'cost_center_id' => $l->cost_center_id,
            'description' => $l->description,
        ])->toArray();

        $reversalData = [
            'date' => now()->toDateString(),
            'description' => $description ?: "Reversal of {$original->je_number}",
            'source_type' => 'manual',
            'lines' => $reversedLines,
        ];

        // Create as submitted so it can be immediately posted (SoD still applies)
        $reversalJe = $this->create($reversalData);

        $reversalJe->update(['reversal_of' => $original->id]);

        return $reversalJe;
    }

    // ── Cancel ───────────────────────────────────────────────────────────────

    public function cancel(JournalEntry $je): JournalEntry
    {
        if ($je->isPosted()) {
            throw new DomainException(
                message: 'Posted journal entries cannot be cancelled. Use a reversing entry instead. (JE-006)',
                errorCode: 'JE_POSTED_CANNOT_CANCEL',
                httpStatus: 422,
                context: ['je_id' => $je->id],
            );
        }

        $je->update(['status' => 'cancelled']);

        return $je->fresh();
    }

    // ── Internal helpers ─────────────────────────────────────────────────────

    /** JE-001: Throws UnbalancedJournalEntryException if debits ≠ credits. */
    private function assertBalanced(array $lines): void
    {
        $totalDebits = collect($lines)->sum(fn ($l) => (float) ($l['debit'] ?? 0));
        $totalCredits = collect($lines)->sum(fn ($l) => (float) ($l['credit'] ?? 0));

        if (abs($totalDebits - $totalCredits) > 0.005) {
            throw new UnbalancedJournalEntryException($totalDebits, $totalCredits);
        }
    }

    private function assertDraftOrStale(JournalEntry $je, string $targetStatus): void
    {
        if (! in_array($je->status, ['draft', 'stale'], true)) {
            throw new DomainException(
                message: "Cannot move journal entry to '{$targetStatus}' from '{$je->status}'.",
                errorCode: 'INVALID_JE_STATUS_TRANSITION',
                httpStatus: 409,
                context: ['je_id' => $je->id, 'from' => $je->status, 'to' => $targetStatus],
            );
        }
    }

    /**
     * JE-009: Generate JE number in format JE-{YYYY}-{MM}-{NNNNNN}.
     * Sequence is per-month; uses DB count of posted JEs in same month.
     */
    private function generateJeNumber(string $date): string
    {
        $d = Carbon::parse($date);
        $yyyy = $d->format('Y');
        $mm = $d->format('m');

        $count = JournalEntry::where('status', 'posted')
            ->whereYear('date', $yyyy)
            ->whereMonth('date', $mm)
            ->count();

        $seq = str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);

        return "JE-{$yyyy}-{$mm}-{$seq}";
    }
}
