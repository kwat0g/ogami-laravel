<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Domains\AP\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $rfq_id
 * @property int         $vendor_id
 * @property string      $status              invited|quoted|declined
 * @property int|null    $quoted_amount_centavos
 * @property int|null    $lead_time_days
 * @property string|null $vendor_remarks
 * @property bool        $is_selected
 * @property \Illuminate\Support\Carbon|null $responded_at
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 */
final class VendorRfqVendor extends Model
{
    protected $table = 'vendor_rfq_vendors';

    protected $fillable = [
        'rfq_id',
        'vendor_id',
        'status',
        'quoted_amount_centavos',
        'lead_time_days',
        'vendor_remarks',
        'responded_at',
        'is_selected',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'is_selected'  => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<VendorRfq, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(VendorRfq::class, 'rfq_id');
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
