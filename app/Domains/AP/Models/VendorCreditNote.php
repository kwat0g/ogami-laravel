<?php

declare(strict_types=1);

namespace App\Domains\AP\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $cn_reference CN-AP-YYYY-MM-NNNNN
 * @property int $vendor_id
 * @property int|null $vendor_invoice_id
 * @property string $note_type credit|debit
 * @property Carbon $note_date
 * @property int $amount_centavos
 * @property string $reason
 * @property string $status draft|posted
 * @property int|null $journal_entry_id
 * @property int $ap_account_id
 * @property int $created_by_id
 * @property Carbon|null $posted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
final class VendorCreditNote extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'vendor_credit_notes';

    protected $fillable = [
        'cn_reference',
        'vendor_id',
        'vendor_invoice_id',
        'note_type',
        'note_date',
        'amount_centavos',
        'reason',
        'status',
        'journal_entry_id',
        'ap_account_id',
        'created_by_id',
        'posted_at',
    ];

    protected $casts = [
        'note_date' => 'date',
        'posted_at' => 'datetime',
    ];

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<VendorInvoice, $this> */
    public function vendorInvoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
