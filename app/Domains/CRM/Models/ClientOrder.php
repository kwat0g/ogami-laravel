<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Domains\AR\Models\Customer;
use App\Domains\Production\Models\DeliverySchedule;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Client Order - Orders submitted by clients through the client portal
 */
class ClientOrder extends Model
{
    use HasFactory;
    use HasPublicUlid;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'order_reference',
        'status',
        'requested_delivery_date',
        'agreed_delivery_date',
        'total_amount_centavos',
        'client_notes',
        'internal_notes',
        'rejection_reason',
        'negotiation_reason',
        'negotiation_notes',
        'negotiation_turn',
        'negotiation_round',
        'last_negotiation_by',
        'last_negotiation_at',
        'last_proposal',
        'delivery_schedule_id',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'submitted_by',
        'submitted_at',
        'vp_approved_by',
        'vp_approved_at',
        'cancelled_by',
        'cancelled_at',
        'sla_deadline',
    ];

    protected $casts = [
        'requested_delivery_date' => 'date',
        'agreed_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'submitted_at' => 'datetime',
        'last_negotiation_at' => 'datetime',
        'vp_approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'sla_deadline' => 'datetime',
        'negotiation_round' => 'integer',
        'last_proposal' => 'array',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_NEGOTIATING = 'negotiating';           // Sales made proposal, waiting for client

    public const STATUS_CLIENT_RESPONDED = 'client_responded'; // Client made counter-proposal, waiting for sales

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_VP_PENDING = 'vp_pending'; // Awaiting VP approval (high-value orders)

    public const STATUS_IN_PRODUCTION = 'in_production';

    public const STATUS_READY_FOR_DELIVERY = 'ready_for_delivery';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FULFILLED = 'fulfilled';

    // Turn constants for negotiation
    public const TURN_SALES = 'sales';

    public const TURN_CLIENT = 'client';

    // Maximum negotiation rounds to prevent infinite loops
    public const MAX_NEGOTIATION_ROUNDS = 5;

    // Negotiation reason constants
    public const NEGOTIATION_STOCK_LOW = 'stock_low';

    public const NEGOTIATION_PRODUCTION_DELAY = 'production_delay';

    public const NEGOTIATION_PRICE_CHANGE = 'price_change';

    public const NEGOTIATION_PARTIAL_FULFILLMENT = 'partial_fulfillment';

    public const NEGOTIATION_OTHER = 'other';

    public static function getNegotiationReasons(): array
    {
        return [
            self::NEGOTIATION_STOCK_LOW => 'Insufficient stock - proposed delivery date',
            self::NEGOTIATION_PRODUCTION_DELAY => 'Production delay - new ETA',
            self::NEGOTIATION_PRICE_CHANGE => 'Price changed due to material cost',
            self::NEGOTIATION_PARTIAL_FULFILLMENT => 'Partial fulfillment available',
            self::NEGOTIATION_OTHER => 'Other - please contact sales',
        ];
    }

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClientOrderItem::class)->orderBy('line_order');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ClientOrderActivity::class)->orderBy('created_at', 'desc');
    }

    /**
     * @deprecated Use deliverySchedules() for multi-item support
     */
    public function deliverySchedule(): BelongsTo
    {
        return $this->belongsTo(DeliverySchedule::class);
    }

    /**
     * All delivery schedules created for this order (one per item)
     */
    public function deliverySchedules(): HasMany
    {
        return $this->hasMany(ClientOrderDeliverySchedule::class)->with('deliverySchedule');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function vpApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vp_approved_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeNegotiated(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_NEGOTIATING, self::STATUS_CLIENT_RESPONDED], true);
    }

    public function isAwaitingSalesResponse(): bool
    {
        return $this->status === self::STATUS_CLIENT_RESPONDED;
    }

    public function isAwaitingClientResponse(): bool
    {
        return $this->status === self::STATUS_NEGOTIATING;
    }

    public function hasReachedMaxNegotiationRounds(): bool
    {
        return $this->negotiation_round >= self::MAX_NEGOTIATION_ROUNDS;
    }

    public function getTotalAmount(): float
    {
        return $this->total_amount_centavos / 100;
    }

    public function getFormattedTotal(): string
    {
        return '₱'.number_format($this->getTotalAmount(), 2);
    }
}
