<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A single line imported from a bank statement.
 *
 * @property int $id
 * @property int $bank_account_id
 * @property Carbon $transaction_date
 * @property string $description
 * @property float $amount Always positive; direction determined by transaction_type
 * @property string $transaction_type debit|credit
 * @property string|null $reference_number
 * @property string $status unmatched|matched|reconciled
 * @property int|null $journal_entry_line_id
 * @property int|null $bank_reconciliation_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read BankAccount               $bankAccount
 * @property-read JournalEntryLine|null     $journalEntryLine
 * @property-read BankReconciliation|null   $reconciliation
 */
final class BankTransaction extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'bank_transactions';

    protected $fillable = [
        'bank_account_id',
        'transaction_date',
        'description',
        'amount',
        'transaction_type',
        'reference_number',
        'status',
        'journal_entry_line_id',
        'bank_reconciliation_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:4',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function journalEntryLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class, 'journal_entry_line_id');
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeUnmatched(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'unmatched');
    }

    public function scopeMatched(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'matched');
    }

    public function scopeReconciled(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'reconciled');
    }

    // ── Business helpers ─────────────────────────────────────────────────────

    public function isUnmatched(): bool
    {
        return $this->status === 'unmatched';
    }

    public function isMatched(): bool
    {
        return $this->status === 'matched';
    }

    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }

    public function isDebit(): bool
    {
        return $this->transaction_type === 'debit';
    }

    public function isCredit(): bool
    {
        return $this->transaction_type === 'credit';
    }
}
