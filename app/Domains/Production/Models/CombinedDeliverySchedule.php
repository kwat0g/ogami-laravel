<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Domains\AR\Models\Customer;
use App\Domains\CRM\Models\ClientOrder;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Combined Delivery Schedule - Groups multiple item schedules into one delivery
 *
 * @property int $id
 * @property string $ulid
 * @property int $client_order_id
 * @property int $customer_id
 * @property string $cds_reference
 * @property string $status
 * @property string|null $target_delivery_date
 * @property string|null $actual_delivery_date
 * @property string|null $delivery_address
 * @property string|null $delivery_instructions
 * @property array|null $item_status_summary
 * @property int $total_items
 * @property int $ready_items
 * @property int $missing_items
 * @property int|null $dispatched_by_id
 * @property string|null $dispatched_at
 * @property-read Collection<int, DeliverySchedule> $itemSchedules
 * @property-read ClientOrder $clientOrder
 * @property-read Customer $customer
 */
final class CombinedDeliverySchedule extends Model implements AuditableContract
{
    use Auditable, HasFactory, HasPublicUlid, SoftDeletes;

    protected $table = 'combined_delivery_schedules';

    public const STATUS_PLANNING = 'planning';

    public const STATUS_READY = 'ready';

    public const STATUS_PARTIALLY_READY = 'partially_ready';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'client_order_id',
        'customer_id',
        'cds_reference',
        'status',
        'target_delivery_date',
        'actual_delivery_date',
        'delivery_address',
        'delivery_instructions',
        'item_status_summary',
        'total_items',
        'ready_items',
        'missing_items',
        'dispatched_by_id',
        'dispatched_at',
        'created_by_id',
    ];

    protected $casts = [
        'target_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'dispatched_at' => 'datetime',
        'item_status_summary' => 'array',
    ];

    public function clientOrder(): BelongsTo
    {
        return $this->belongsTo(ClientOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function itemSchedules(): HasMany
    {
        return $this->hasMany(DeliverySchedule::class, 'combined_delivery_schedule_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by_id');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isFullyDeliverable(): bool
    {
        return $this->ready_items === $this->total_items;
    }

    public function getMissingItemsCount(): int
    {
        return $this->total_items - $this->ready_items;
    }

    public function updateItemStatusSummary(): void
    {
        $schedules = $this->itemSchedules;
        $this->total_items = $schedules->count();
        $this->ready_items = $schedules->whereIn('status', ['ready', 'dispatched', 'delivered'])->count();
        $this->missing_items = $schedules->where('status', 'open')->count();

        // Build detailed summary
        $summary = [];
        foreach ($schedules as $schedule) {
            $summary[] = [
                'delivery_schedule_id' => $schedule->id,
                'product_name' => $schedule->productItem?->name,
                'qty_ordered' => $schedule->qty_ordered,
                'status' => $schedule->status,
                'is_ready' => in_array($schedule->status, ['ready', 'dispatched', 'delivered']),
            ];
        }
        $this->item_status_summary = $summary;

        // Update overall status
        if ($this->ready_items === 0) {
            $this->status = self::STATUS_PLANNING;
        } elseif ($this->ready_items === $this->total_items) {
            $this->status = self::STATUS_READY;
        } else {
            $this->status = self::STATUS_PARTIALLY_READY;
        }

        $this->save();
    }
}
