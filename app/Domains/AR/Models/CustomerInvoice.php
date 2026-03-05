<?php

declare(strict_types=1);

namespace App\Domains\AR\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string|null $invoice_number AR-003: INV-YYYY-MM-NNNNNN, set on approval
 * @property int $customer_id
 * @property int $fiscal_period_id
 * @property int $ar_account_id
 * @property int $revenue_account_id
 * @property \Carbon\Carbon $invoice_date
 * @property \Carbon\Carbon $due_date
 * @property float $subtotal
 * @property float $vat_amount
 * @property float $total_amount stored as subtotal + vat_amount
 * @property string|null $vat_exemption_reason
 * @property string|null $description
 * @property string $status draft|approved|partially_paid|paid|written_off|cancelled
 * @property string|null $write_off_reason
 * @property int|null $write_off_approved_by
 * @property \Carbon\Carbon|null $write_off_at
 * @property int|null $journal_entry_id
 * @property int|null $write_off_journal_entry_id
 * @property int $created_by
 * @property int|null $approved_by
 * @property \Carbon\Carbon|null $approved_at
 * @property-read float $total_paid
 * @property-read float $balance_due
 * @property-read bool  $is_overdue
 */
class CustomerInvoice extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'fiscal_period_id',
        'ar_account_id',
        'revenue_account_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'vat_amount',
        'vat_exemption_reason',
        'description',
        'status',
        'write_off_reason',
        'write_off_approved_by',
        'write_off_at',
        'journal_entry_id',
        'write_off_journal_entry_id',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'approved_at' => 'datetime',
        'write_off_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    // ── Computed Accessors ─────────────────────────────────────────────────────

    public function getTotalPaidAttribute(): float
    {
        return round((float) $this->payments()->sum('amount'), 2);
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0.0, round((float) $this->total_amount - $this->total_paid, 2));
    }

    public function getIsOverdueAttribute(): bool
    {
        if (in_array($this->status, ['paid', 'written_off', 'cancelled'], true)) {
            return false;
        }

        return $this->due_date->isPast();
    }

    // ── Status Helpers ─────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'partially_paid', 'paid'], true);
    }

    /** Only draft invoices can be edited. */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    // ── Query Scopes ───────────────────────────────────────────────────────────

    public function scopeOverdue(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->whereNotIn('status', ['paid', 'written_off', 'cancelled'])
            ->where('due_date', '<', now()->toDateString());
    }

    public function scopeDueSoon(\Illuminate\Database\Eloquent\Builder $query, int $days = 7): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->whereNotIn('status', ['paid', 'written_off', 'cancelled'])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeByStatus(\Illuminate\Database\Eloquent\Builder $query, string|array $status): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('status', (array) $status);
    }
}
