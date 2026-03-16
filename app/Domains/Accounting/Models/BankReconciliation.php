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
 * Bank Reconciliation — matches bank statement entries to GL journal entry
 * lines for a given bank account and date range.
 *
 * SoD: certifier cannot be the drafter (enforced in service + DB CHECK).
 *
 * @property int $id
 * @property int $bank_account_id
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property float $opening_balance
 * @property float $closing_balance
 * @property string $status draft|certified
 * @property int $created_by
 * @property int|null $certified_by
 * @property Carbon|null $certified_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read BankAccount                      $bankAccount
 * @property-read User                 $creator
 * @property-read User|null            $certifier
 * @property-read Collection<int, BankTransaction> $transactions
 */
final class BankReconciliation extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'bank_reconciliations';

    protected $fillable = [
        'bank_account_id',
        'period_from',
        'period_to',
        'opening_balance',
        'closing_balance',
        'status',
        'created_by',
        'certified_by',
        'certified_at',
        'notes',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'opening_balance' => 'decimal:4',
        'closing_balance' => 'decimal:4',
        'certified_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function certifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'bank_reconciliation_id');
    }

    // ── Business helpers ─────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCertified(): bool
    {
        return $this->status === 'certified';
    }

    /**
     * Count of bank transactions not yet matched to a GL line in this reconciliation.
     */
    public function unmatchedCount(): int
    {
        return $this->transactions()->where('status', 'unmatched')->count();
    }
}
