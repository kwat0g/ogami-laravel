<?php

declare(strict_types=1);

namespace App\Domains\AP\Models;

use App\Models\User;
use App\Shared\Exceptions\DomainException;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Vendor — supplier master record.
 *
 * @property int $id
 * @property string $name
 * @property string|null $tin AP-011: required before payment can be made
 * @property int|null $ewt_rate_id
 * @property string|null $atc_code
 * @property bool $is_ewt_subject AP-004
 * @property bool $is_active AP-002: invoice blocked when false
 * @property string $accreditation_status pending|accredited|suspended|blacklisted
 * @property string|null $accreditation_notes
 * @property string|null $bank_name
 * @property string|null $bank_account_no
 * @property string|null $bank_account_name
 * @property string|null $payment_terms
 * @property int $lead_time_days Default days from PO send to expected delivery
 * @property string|null $address
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $notes
 * @property int $created_by
 * @property-read EwtRate|null $ewtRate
 */
final class Vendor extends Model implements Auditable
{
    use AuditableTrait, HasFactory, SoftDeletes;

    protected $table = 'vendors';

    protected static function newFactory(): VendorFactory
    {
        return VendorFactory::new();
    }

    protected $fillable = [
        'name',
        'tin',
        'ewt_rate_id',
        'atc_code',
        'is_ewt_subject',
        'is_active',
        'accreditation_status',
        'accreditation_notes',
        'bank_name',
        'bank_account_no',
        'bank_account_name',
        'payment_terms',
        'lead_time_days',
        'address',
        'contact_person',
        'email',
        'phone',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'is_ewt_subject' => 'boolean',
        'is_active' => 'boolean',
        'lead_time_days' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    /** @return BelongsTo<EwtRate, $this> */
    public function ewtRate(): BelongsTo
    {
        return $this->belongsTo(EwtRate::class, 'ewt_rate_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class, 'vendor_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class, 'vendor_id');
    }

    public function portalUser(): HasOne
    {
        return $this->hasOne(User::class, 'vendor_id');
    }

    /** @return HasMany<VendorItem, Vendor> */
    public function vendorItems(): HasMany
    {
        return $this->hasMany(VendorItem::class, 'vendor_id');
    }

    // ── Business helpers ─────────────────────────────────────────────────────

    /** AP-002: vendor must be active to accept new invoices. */
    public function assertActive(): void
    {
        if (! $this->is_active) {
            throw new DomainException(
                message: "Vendor '{$this->name}' is inactive and cannot accept new invoices. (AP-002)",
                errorCode: 'VENDOR_INACTIVE',
                httpStatus: 422,
            );
        }
    }

    /** AP-011: vendor needs TIN before payment can be recorded. */
    public function hasTin(): bool
    {
        return filled($this->tin);
    }

    /** Procurement: vendor must be accredited to be used on a PO. */
    public function assertAccredited(): void
    {
        if ($this->accreditation_status !== 'accredited') {
            throw new DomainException(
                message: "Vendor '{$this->name}' is not accredited (status: {$this->accreditation_status}). Accreditation is required before creating a Purchase Order.",
                errorCode: 'VENDOR_NOT_ACCREDITED',
                httpStatus: 422,
            );
        }
    }

    public function isAccredited(): bool
    {
        return $this->accreditation_status === 'accredited';
    }
}
