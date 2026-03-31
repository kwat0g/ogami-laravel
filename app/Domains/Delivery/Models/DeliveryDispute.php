<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Models;

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerCreditNote;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\CRM\Models\Ticket;
use App\Domains\Production\Models\DeliverySchedule;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * DeliveryDispute -- raised when a client reports issues during delivery acknowledgment.
 *
 * @property int $id
 * @property string $ulid
 * @property string $dispute_reference
 * @property int|null $delivery_schedule_id
 * @property int|null $client_order_id
 * @property int $customer_id
 * @property int|null $delivery_receipt_id
 * @property int $reported_by_id
 * @property int|null $assigned_to_id
 * @property string $status open|investigating|pending_resolution|resolved|closed
 * @property string|null $resolution_type replace_items|credit_note|partial_accept|full_replacement
 * @property string|null $resolution_notes
 * @property string|null $client_notes
 * @property int|null $resolved_by_id
 * @property string|null $resolved_at
 * @property int|null $replacement_schedule_id
 * @property int|null $credit_note_id
 * @property int|null $ticket_id
 */
final class DeliveryDispute extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'delivery_disputes';

    protected $fillable = [
        'dispute_reference',
        'delivery_schedule_id',
        'client_order_id',
        'customer_id',
        'delivery_receipt_id',
        'reported_by_id',
        'assigned_to_id',
        'status',
        'resolution_type',
        'resolution_notes',
        'client_notes',
        'resolved_by_id',
        'resolved_at',
        'replacement_schedule_id',
        'credit_note_id',
        'ticket_id',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryDisputeItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function clientOrder(): BelongsTo
    {
        return $this->belongsTo(ClientOrder::class);
    }

    public function deliverySchedule(): BelongsTo
    {
        return $this->belongsTo(DeliverySchedule::class);
    }

    public function deliveryReceipt(): BelongsTo
    {
        return $this->belongsTo(DeliveryReceipt::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    public function replacementSchedule(): BelongsTo
    {
        return $this->belongsTo(DeliverySchedule::class, 'replacement_schedule_id');
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditNote::class, 'credit_note_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
