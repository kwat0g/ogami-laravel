<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Domains\AR\Models\Customer;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Inventory\Models\ItemMaster;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Multi-item Delivery Schedule — groups all items for a single delivery.
 *
 * @property int $id
 * @property string $ulid
 * @property string $ds_reference
 * @property int $customer_id
 * @property int|null $client_order_id
 * @property int|null $product_item_id   Legacy — use items() for multi-item
 * @property string|null $qty_ordered    Legacy — use items() for multi-item
 * @property string $target_delivery_date
 * @property string|null $actual_delivery_date
 * @property string $type
 * @property string $status
 * @property string|null $notes
 * @property string|null $delivery_address
 * @property string|null $delivery_instructions
 * @property array|null $item_status_summary
 * @property int $total_items
 * @property int $ready_items
 * @property int $missing_items
 * @property bool $has_dispute
 * @property array|null $dispute_summary
 * @property int|null $dispatched_by_id
 * @property string|null $dispatched_at
 * @property int|null $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class DeliverySchedule extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'delivery_schedules';

    protected $fillable = [
        'ulid',
        'customer_id',
        'client_order_id',
        'product_item_id',
        'qty_ordered',
        'unit_price',
        'target_delivery_date',
        'actual_delivery_date',
        'type',
        'status',
        'notes',
        'delivery_address',
        'delivery_instructions',
        'item_status_summary',
        'total_items',
        'ready_items',
        'missing_items',
        'has_dispute',
        'dispute_summary',
        'dispute_resolved_at',
        'dispatched_by_id',
        'dispatched_at',
        'created_by_id',
        'client_acknowledgment',
        'combined_delivery_schedule_id',
        'delivery_receipt_id',
    ];

    protected $casts = [
        'qty_ordered' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'target_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'dispatched_at' => 'datetime',
        'dispute_resolved_at' => 'datetime',
        'client_acknowledgment' => 'array',
        'item_status_summary' => 'array',
        'dispute_summary' => 'array',
        'has_dispute' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function clientOrder(): BelongsTo
    {
        return $this->belongsTo(ClientOrder::class, 'client_order_id');
    }

    /** @deprecated Use items() for multi-item support */
    public function productItem(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'product_item_id');
    }

    /** Multi-item children */
    public function items(): HasMany
    {
        return $this->hasMany(DeliveryScheduleItem::class, 'delivery_schedule_id');
    }

    /** All production orders linked through items */
    public function productionOrders(): HasManyThrough
    {
        return $this->hasManyThrough(
            ProductionOrder::class,
            DeliveryScheduleItem::class,
            'delivery_schedule_id',       // DSI FK on delivery_schedule_items
            'delivery_schedule_item_id',  // PO FK on production_orders
            'id',                         // DS local key
            'id'                          // DSI local key
        );
    }

    /** Legacy: direct production orders (old per-item DS records) */
    public function legacyProductionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'delivery_schedule_id');
    }

    public function deliveryReceipts(): HasMany
    {
        return $this->hasMany(DeliveryReceipt::class, 'delivery_schedule_id');
    }

    /** @deprecated Kept for backward compat during migration */
    public function combinedDeliverySchedule(): BelongsTo
    {
        return $this->belongsTo(CombinedDeliverySchedule::class, 'combined_delivery_schedule_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isFullyDeliverable(): bool
    {
        return $this->ready_items === $this->total_items && $this->total_items > 0;
    }

    /**
     * Recalculate item status summary from child items.
     */
    public function updateItemStatusSummary(): void
    {
        $items = $this->items()->with('productItem')->get();

        $summary = $items->map(fn (DeliveryScheduleItem $item) => [
            'delivery_schedule_item_id' => $item->id,
            'product_name' => $item->productItem?->name,
            'qty_ordered' => (string) $item->qty_ordered,
            'status' => $item->status,
            'is_ready' => $item->status === 'ready',
            'is_missing' => in_array($item->status, ['pending', 'in_production'], true),
        ])->toArray();

        $readyCount = collect($summary)->where('is_ready', true)->count();
        $totalCount = count($summary);
        $missingCount = $totalCount - $readyCount;

        // Determine parent status based on item statuses
        $newStatus = $this->status;
        if ($totalCount > 0) {
            if ($readyCount === $totalCount) {
                $newStatus = 'ready';
            } elseif ($readyCount > 0) {
                $newStatus = 'partially_ready';
            }
        }

        $this->update([
            'item_status_summary' => $summary,
            'total_items' => $totalCount,
            'ready_items' => $readyCount,
            'missing_items' => $missingCount,
            'status' => $newStatus,
        ]);
    }
}
