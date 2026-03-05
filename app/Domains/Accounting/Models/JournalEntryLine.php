<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Journal Entry Line — one debit or credit line in a JE.
 *
 * @property int $id
 * @property int $journal_entry_id
 * @property int $account_id
 * @property float|null $debit > 0 if set; mutually exclusive with credit (JE-003)
 * @property float|null $credit > 0 if set; mutually exclusive with debit  (JE-003)
 * @property int|null $cost_center_id
 * @property string|null $description
 * @property-read JournalEntry  $journalEntry
 * @property-read ChartOfAccount $account
 */
final class JournalEntryLine extends Model implements Auditable
{
    use AuditableTrait;

    public $timestamps = false;

    protected $table = 'journal_entry_lines';

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'cost_center_id',
        'description',
    ];

    protected $casts = [
        'debit' => 'float',
        'credit' => 'float',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    // ── Business helpers ─────────────────────────────────────────────────────

    /**
     * Returns the signed amount: debit = positive, credit = negative.
     * Useful for computing running balances by account normal_balance.
     */
    public function signedAmount(): float
    {
        return $this->debit ?? -($this->credit ?? 0.0);
    }
}
