<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Fiscal Period — an accounting calendar interval (usually one month).
 *
 * @property int $id
 * @property string $name e.g. "Feb 2026"
 * @property string $date_from
 * @property string $date_to
 * @property string $status open | closed
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read \App\Models\User|null             $closer
 * @property-read Collection<int, JournalEntry>     $journalEntries
 */
final class FiscalPeriod extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'fiscal_periods';

    protected $fillable = [
        'name',
        'date_from',
        'date_to',
        'status',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'closed_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function closer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'closed_by');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'fiscal_period_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeOpen(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'open');
    }

    // ── Business helpers ─────────────────────────────────────────────────────

    /**
     * Returns true if the given date falls within this fiscal period.
     */
    public function containsDate(Carbon $date): bool
    {
        return $date->between(
            Carbon::parse($this->date_from)->startOfDay(),
            Carbon::parse($this->date_to)->endOfDay()
        );
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
