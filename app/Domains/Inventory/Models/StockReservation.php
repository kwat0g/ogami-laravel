<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Stock Reservation - Reserves inventory for specific purposes.
 *
 * @property int $id
 * @property int $item_id
 * @property int|null $location_id
 * @property numeric-string $quantity_reserved
 * @property string $reservation_type production_order|delivery_schedule|safety_stock
 * @property int $reference_id
 * @property string $reference_type
 * @property string $status active|fulfilled|cancelled|expired
 * @property Carbon $reserved_at
 * @property Carbon|null $expires_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class StockReservation extends Model
{
    protected $table = 'stock_reservations';

    protected $fillable = [
        'item_id',
        'location_id',
        'quantity_reserved',
        'reservation_type',
        'reference_id',
        'reference_type',
        'status',
        'reserved_at',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'quantity_reserved' => 'decimal:4',
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<ItemMaster, StockReservation> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }

    /** @return BelongsTo<WarehouseLocation, StockReservation> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    /**
     * Scope for active reservations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for reservations that haven't expired.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Mark reservation as fulfilled.
     */
    public function fulfill(): void
    {
        $this->update(['status' => 'fulfilled']);
        $this->updateStockBalanceReserved();
    }

    /**
     * Cancel the reservation.
     */
    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'notes' => $reason ?? $this->notes,
        ]);
        $this->updateStockBalanceReserved();
    }

    /**
     * Mark reservation as expired.
     */
    public function expire(): void
    {
        $this->update(['status' => 'expired']);
        $this->updateStockBalanceReserved();
    }

    /**
     * Update the reserved quantity on stock_balances.
     */
    private function updateStockBalanceReserved(): void
    {
        // Recalculate total reserved for this item/location
        $totalReserved = self::where('item_id', $this->item_id)
            ->where('location_id', $this->location_id)
            ->where('status', 'active')
            ->sum('quantity_reserved');

        StockBalance::updateOrCreate(
            [
                'item_id' => $this->item_id,
                'location_id' => $this->location_id,
            ],
            ['quantity_reserved' => $totalReserved]
        );
    }
}
