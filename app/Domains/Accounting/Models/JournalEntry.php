<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Journal Entry — header record for a double-entry GL transaction.
 *
 * @property int $id
 * @property string|null $je_number JE-YYYY-MM-NNNNNN; set on posting (JE-009)
 * @property string $date
 * @property string $description
 * @property string $source_type manual|payroll|ap|ar
 * @property int|null $source_id FK to source domain record (JE-008)
 * @property string $status draft|submitted|posted|cancelled|stale
 * @property int $fiscal_period_id
 * @property int|null $reversal_of FK to original JE (JE-007)
 * @property int $created_by
 * @property int|null $submitted_by
 * @property int|null $posted_by
 * @property Carbon|null $posted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FiscalPeriod                         $fiscalPeriod
 * @property-read JournalEntry|null                    $reversalOf
 * @property-read Collection<int, JournalEntryLine>    $lines
 * @property-read User                     $creator
 * @property-read User|null                $submitter
 * @property-read User|null                $poster
 */
final class JournalEntry extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes, HasPublicUlid, SoftDeletes;

    protected $table = 'journal_entries';

    protected $fillable = [
        'je_number',
        'date',
        'description',
        'source_type',
        'source_id',
        'status',
        'fiscal_period_id',
        'reversal_of',
        'created_by',
        'submitted_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'date' => 'date',
        'posted_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    // ── Business helpers ─────────────────────────────────────────────────────

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * JE-008: Returns true if this JE was auto-created by the system (not manually drafted).
     */
    public function isAutoPosted(): bool
    {
        return $this->source_type !== 'manual';
    }

    /**
     * JE-007: Returns true if this JE has already been reversed.
     */
    public function hasBeenReversed(): bool
    {
        return self::where('reversal_of', $this->id)->exists();
    }
}
