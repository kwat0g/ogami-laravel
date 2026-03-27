<?php

declare(strict_types=1);

namespace App\Domains\AP\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payment_batch_id
 * @property int $vendor_invoice_id
 * @property int $vendor_id
 * @property int $amount_centavos
 * @property string $status pending|paid|failed|skipped
 * @property string|null $remarks
 * @property-read PaymentBatch $batch
 * @property-read VendorInvoice $vendorInvoice
 * @property-read Vendor $vendor
 */
final class PaymentBatchItem extends Model
{
    protected $table = 'payment_batch_items';

    protected $fillable = [
        'payment_batch_id',
        'vendor_invoice_id',
        'vendor_id',
        'amount_centavos',
        'status',
        'remarks',
    ];

    protected $casts = [
        'amount_centavos' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'payment_batch_id');
    }

    public function vendorInvoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class, 'vendor_invoice_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}
