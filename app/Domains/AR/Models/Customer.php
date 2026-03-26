<?php

declare(strict_types=1);

namespace App\Domains\AR\Models;

use App\Models\User;
use App\Shared\Exceptions\DomainException;
use App\Shared\Traits\HasPublicUlid;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $tin
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $contact_person
 * @property string|null $address
 * @property string|null $billing_address
 * @property float $credit_limit
 * @property bool $is_active
 * @property string|null $notes
 * @property int $created_by
 * @property int|null $ar_account_id
 * @property-read float $current_outstanding   AR-004: computed — never a DB column
 * @property-read float $available_credit
 */
class Customer extends Model implements Auditable
{
    use AuditableTrait, HasFactory, HasPublicUlid, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'tin',
        'email',
        'phone',
        'contact_person',
        'address',
        'billing_address',
        'credit_limit',
        'is_active',
        'notes',
        'created_by',
        'ar_account_id',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    /**
     * The client portal user linked to this customer (if provisioned).
     */
    public function portalUser(): HasOne
    {
        return $this->hasOne(User::class, 'client_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(CustomerInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function advancePayments(): HasMany
    {
        return $this->hasMany(CustomerAdvancePayment::class);
    }

    // ── AR-004: current_outstanding — computed accessor, NEVER stored ──────────
    //
    // = SUM(approved_invoice total_amounts) - SUM(receipts) - unapplied advances
    //
    public function getCurrentOutstandingAttribute(): float
    {
        // Use pre-loaded aggregate sums when available (avoids N+1 in list contexts).
        // CustomerService::list() eager-loads `billed_total` and `total_paid` via withSum().
        $billed = isset($this->attributes['billed_total'])
            ? (float) $this->attributes['billed_total']
            : (float) $this->invoices()->whereIn('status', ['approved', 'partially_paid', 'paid'])->sum('total_amount');

        $paid = isset($this->attributes['total_paid'])
            ? (float) $this->attributes['total_paid']
            : (float) $this->payments()->sum('amount');

        $unappliedAdvances = (float) $this->advancePayments()
            ->where('status', '!=', 'fully_applied')
            ->selectRaw('SUM(amount - applied_amount) as unapplied')
            ->value('unapplied');

        return max(0.0, round($billed - $paid - $unappliedAdvances, 2));
    }

    public function getAvailableCreditAttribute(): float
    {
        if ($this->credit_limit <= 0) {
            // No limit set — effectively unlimited
            return PHP_FLOAT_MAX;
        }

        return max(0.0, round((float) $this->credit_limit - $this->current_outstanding, 2));
    }

    // ── AR-001: credit guard ───────────────────────────────────────────────────

    /**
     * Throws a DomainException when the invoice would breach the credit limit.
     * Skipped when credit_limit === 0 (unlimited).
     */
    public function assertCreditAvailable(float $invoiceAmount): void
    {
        if ($this->credit_limit <= 0.0) {
            return; // unlimited
        }

        $projected = $this->current_outstanding + $invoiceAmount;

        if ($projected > (float) $this->credit_limit) {
            throw new DomainException(
                sprintf(
                    'Credit limit exceeded. Limit: %.2f, Current outstanding: %.2f, Invoice amount: %.2f, Projected: %.2f.',
                    $this->credit_limit,
                    $this->current_outstanding,
                    $invoiceAmount,
                    $projected
                ),
                'AR_CREDIT_LIMIT_EXCEEDED',
                422
            );
        }
    }
}
