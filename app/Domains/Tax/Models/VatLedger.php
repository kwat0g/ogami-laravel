<?php

declare(strict_types=1);

namespace App\Domains\Tax\Models;

use App\Exceptions\DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Per-period VAT accumulator (VAT-004).
 *
 * net_vat         = output_vat - input_vat          (stored column)
 * vat_payable     = net_vat - carry_forward_from_prior
 *
 * If vat_payable < 0 the surplus is carried to the next period.
 *
 * @property int $id
 * @property int $fiscal_period_id unique
 * @property float $input_vat
 * @property float $output_vat
 * @property float $carry_forward_from_prior
 * @property float $net_vat storedAs: output_vat - input_vat
 * @property bool $is_closed
 * @property \Carbon\Carbon|null $closed_at
 * @property int|null $closed_by
 * @property-read float $vat_payable
 */
class VatLedger extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'vat_ledger';

    protected $fillable = [
        'fiscal_period_id',
        'input_vat',
        'output_vat',
        'carry_forward_from_prior',
        'is_closed',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'input_vat' => 'decimal:2',
        'output_vat' => 'decimal:2',
        'carry_forward_from_prior' => 'decimal:2',
        'net_vat' => 'decimal:2',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'closed_by');
    }

    // ── Computed Accessors ─────────────────────────────────────────────────────

    /**
     * VAT-004: actual payable after carry-forward deduction.
     *
     * If positive  → amount due to BIR this period.
     * If negative  → VatLedgerService::closePeriod() carries the abs to next period.
     */
    public function getVatPayableAttribute(): float
    {
        return round((float) $this->net_vat - (float) $this->carry_forward_from_prior, 2);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeForPeriod(\Illuminate\Database\Eloquent\Builder $query, int $fiscalPeriodId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('fiscal_period_id', $fiscalPeriodId);
    }

    // ── Actions ────────────────────────────────────────────────────────────────

    public function close(int $userId): void
    {
        if ($this->is_closed) {
            throw new DomainException(
                'VAT ledger for this period is already closed.',
                'TAX_PERIOD_ALREADY_CLOSED',
                422
            );
        }

        $this->update([
            'is_closed' => true,
            'closed_at' => now(),
            'closed_by' => $userId,
        ]);
    }
}
