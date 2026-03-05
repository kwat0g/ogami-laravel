<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $name
 * @property string $account_number
 * @property string $bank_name
 * @property string $account_type checking|savings
 * @property int|null $account_id FK → chart_of_accounts
 * @property float $opening_balance
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ChartOfAccount|null                      $chartAccount
 * @property-read Collection<int, BankTransaction>         $transactions
 * @property-read Collection<int, BankReconciliation>      $reconciliations
 */
final class BankAccount extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $table = 'bank_accounts';

    protected $fillable = [
        'name',
        'account_number',
        'bank_name',
        'account_type',
        'account_id',
        'opening_balance',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'bank_account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class, 'bank_account_id');
    }

    // ── Business helpers ─────────────────────────────────────────────────────

    public function isChecking(): bool
    {
        return $this->account_type === 'checking';
    }

    public function isSavings(): bool
    {
        return $this->account_type === 'savings';
    }
}
