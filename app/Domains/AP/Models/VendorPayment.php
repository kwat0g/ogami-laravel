<?php

declare(strict_types=1);

namespace App\Domains\AP\Models;

use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * VendorPayment — records a single disbursement against a VendorInvoice.
 *
 * @property int $id
 * @property int $vendor_invoice_id
 * @property int $vendor_id
 * @property Carbon $payment_date
 * @property float $amount Always > 0 (AP-008 DB CHECK)
 * @property string|null $reference_number
 * @property string|null $payment_method bank_transfer|check|cash
 * @property string|null $notes
 * @property bool $form_2307_generated Flag set when BIR Form 2307 is generated (AP-009)
 * @property Carbon|null $form_2307_generated_at
 * @property int|null $journal_entry_id
 * @property int $created_by
 */
final class VendorPayment extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $table = 'vendor_payments';

    // Payments are immutable once recorded — no updates allowed via service layer.
    protected $fillable = [
        'vendor_invoice_id',
        'vendor_id',
        'payment_date',
        'amount',
        'reference_number',
        'payment_method',
        'notes',
        'form_2307_generated',
        'form_2307_generated_at',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'form_2307_generated' => 'boolean',
        'form_2307_generated_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function vendorInvoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
