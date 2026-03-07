<?php

declare(strict_types=1);

namespace App\Domains\AP\Models;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * VendorInvoice — AP invoice lifecycle (AP-001 through AP-008).
 *
 * @property int $id
 * @property int $vendor_id
 * @property int $fiscal_period_id
 * @property int $ap_account_id
 * @property int $expense_account_id
 * @property Carbon $invoice_date
 * @property Carbon $due_date Always >= invoice_date (AP-001 DB CHECK)
 * @property float $net_amount
 * @property float $vat_amount
 * @property float $ewt_amount Snapshot at invoice creation (AP-004)
 * @property string|null $or_number
 * @property string|null $vat_exemption_reason
 * @property string|null $atc_code
 * @property float|null $ewt_rate Snapshot of rate at invoice creation
 * @property string $status draft|pending_approval|head_noted|manager_checked|officer_reviewed|approved|partially_paid|paid|deleted
 * @property string|null $rejection_note
 * @property string|null $description
 * @property int|null $journal_entry_id
 * @property int $created_by
 * @property int|null $submitted_by
 * @property int|null $approved_by
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $deleted_at
 *
 * Computed (not stored):
 * @property-read float  $net_payable  net_amount + vat_amount − ewt_amount (AP-005)
 * @property-read float  $total_paid   SUM(payments.amount)
 * @property-read float  $balance_due  net_payable − total_paid
 * @property-read bool   $is_overdue
 */
final class VendorInvoice extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'vendor_invoices';

    protected $fillable = [
        'vendor_id',
        'fiscal_period_id',
        'ap_account_id',
        'expense_account_id',
        'invoice_date',
        'due_date',
        'net_amount',
        'vat_amount',
        'ewt_amount',
        'or_number',
        'vat_exemption_reason',
        'atc_code',
        'ewt_rate',
        'status',
        'rejection_note',
        'description',
        'source',
        'purchase_order_id',
        'journal_entry_id',
        'created_by',
        'submitted_by',
        'approved_by',
        'submitted_at',
        'approved_at',
        'head_noted_by',
        'head_noted_at',
        'manager_checked_by',
        'manager_checked_at',
        'officer_reviewed_by',
        'officer_reviewed_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'net_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'ewt_amount' => 'decimal:2',
        'ewt_rate' => 'decimal:4',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function apAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'ap_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    // ── Computed Accessors ────────────────────────────────────────────────────

    /**
     * Net amount payable to vendor: net + VAT − EWT (AP-005).
     * Never stored in DB; always calculated on-the-fly.
     */
    public function getNetPayableAttribute(): float
    {
        return (float) $this->net_amount
             + (float) $this->vat_amount
             - (float) $this->ewt_amount;
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getBalanceDueAttribute(): float
    {
        return $this->net_payable - $this->total_paid;
    }

    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, ['approved', 'partially_paid'], true)
            && $this->due_date->isPast();
    }

    // ── Status Helpers ────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isHeadNoted(): bool
    {
        return $this->status === 'head_noted';
    }

    public function isManagerChecked(): bool
    {
        return $this->status === 'manager_checked';
    }

    public function isOfficerReviewed(): bool
    {
        return $this->status === 'officer_reviewed';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isFullyPaid(): bool
    {
        return $this->status === 'paid';
    }

    /** AP-006: Invoices may only be edited while in draft. */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** All open invoices past their due date. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', ['approved', 'partially_paid'])
            ->where('due_date', '<', Carbon::today());
    }

    /** Open invoices due within the next $days calendar days. */
    public function scopeDueSoon(Builder $query, int $days = 7): Builder
    {
        return $query
            ->whereIn('status', ['approved', 'partially_paid'])
            ->whereBetween('due_date', [Carbon::today(), Carbon::today()->addDays($days)]);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
