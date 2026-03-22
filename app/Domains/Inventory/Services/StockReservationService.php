<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\StockReservation;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stock Reservation Service - Manages inventory reservations.
 * 
 * Ensures stock is reserved for:
 * - Production Orders (hard reservation when released)
 * - Delivery Schedules (soft reservation when confirmed)
 * - Safety Stock (permanent reservation)
 */
final class StockReservationService implements ServiceContract
{
    /**
     * Create a new stock reservation.
     *
     * @throws DomainException if insufficient stock
     */
    public function createReservation(
        int $itemId,
        float $quantity,
        string $reservationType,
        int $referenceId,
        string $referenceType,
        ?int $locationId = null,
        ?Carbon $expiresAt = null,
        ?string $notes = null,
    ): StockReservation {
        return DB::transaction(function () use (
            $itemId, $quantity, $reservationType, $referenceId, 
            $referenceType, $locationId, $expiresAt, $notes
        ) {
            // Check available stock
            $availableStock = $this->getAvailableStock($itemId, $locationId);
            
            if ($availableStock < $quantity) {
                throw new DomainException(
                    sprintf(
                        'Insufficient stock for reservation. Available: %.4f, Requested: %.4f',
                        $availableStock,
                        $quantity
                    ),
                    'INSUFFICIENT_STOCK',
                    422,
                );
            }

            // Create reservation
            $reservation = StockReservation::create([
                'item_id' => $itemId,
                'location_id' => $locationId,
                'quantity_reserved' => $quantity,
                'reservation_type' => $reservationType,
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
                'status' => 'active',
                'reserved_at' => now(),
                'expires_at' => $expiresAt,
                'notes' => $notes,
            ]);

            // Update stock balance reserved quantity
            $this->updateStockBalanceReserved($itemId, $locationId);

            Log::info("Created stock reservation #{$reservation->id} for item {$itemId}, qty: {$quantity}");

            return $reservation;
        });
    }

    /**
     * Get available stock (On Hand - Reserved).
     */
    public function getAvailableStock(int $itemId, ?int $locationId = null): float
    {
        $query = StockBalance::where('item_id', $itemId);
        
        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        $balance = $query->first();

        if ($balance === null) {
            return 0;
        }

        $onHand = (float) $balance->quantity_on_hand;
        $reserved = (float) $balance->quantity_reserved;

        return max(0, $onHand - $reserved);
    }

    /**
     * Get available stock across all locations for an item.
     */
    public function getTotalAvailableStock(int $itemId): float
    {
        return StockBalance::where('item_id', $itemId)
            ->get()
            ->sum(fn ($b) => max(0, (float) $b->quantity_on_hand - (float) $b->quantity_reserved));
    }

    /**
     * Cancel a reservation.
     */
    public function cancelReservation(StockReservation $reservation, ?string $reason = null): void
    {
        DB::transaction(function () use ($reservation, $reason) {
            $reservation->cancel($reason);
            $this->updateStockBalanceReserved($reservation->item_id, $reservation->location_id);
            
            Log::info("Cancelled stock reservation #{$reservation->id}");
        });
    }

    /**
     * Fulfill a reservation (when stock is actually used).
     */
    public function fulfillReservation(StockReservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            $reservation->fulfill();
            $this->updateStockBalanceReserved($reservation->item_id, $reservation->location_id);
            
            Log::info("Fulfilled stock reservation #{$reservation->id}");
        });
    }

    /**
     * Cancel existing reservations and create new one.
     * Used when updating production orders or delivery schedules.
     */
    public function replaceReservation(
        int $referenceId,
        string $referenceType,
        int $itemId,
        float $newQuantity,
        ?int $locationId = null,
        ?string $notes = null,
    ): StockReservation {
        return DB::transaction(function () use (
            $referenceId, $referenceType, $itemId, $newQuantity, $locationId, $notes
        ) {
            // Cancel existing reservations for this reference
            StockReservation::where('reference_id', $referenceId)
                ->where('reference_type', $referenceType)
                ->where('item_id', $itemId)
                ->where('status', 'active')
                ->get()
                ->each(fn ($r) => $r->cancel('Replaced by new reservation'));

            // Create new reservation
            return $this->createReservation(
                itemId: $itemId,
                quantity: $newQuantity,
                reservationType: 'production_order',
                referenceId: $referenceId,
                referenceType: $referenceType,
                locationId: $locationId,
                notes: $notes,
            );
        });
    }

    /**
     * Get all reservations for a reference.
     *
     * @return Collection<int, StockReservation>
     */
    public function getReservationsForReference(int $referenceId, string $referenceType): Collection
    {
        return StockReservation::where('reference_id', $referenceId)
            ->where('reference_type', $referenceType)
            ->with('item')
            ->get();
    }

    /**
     * Expire old reservations.
     * Should be run daily via scheduler.
     */
    public function expireOldReservations(): int
    {
        $expired = StockReservation::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $reservation) {
            $reservation->expire();
            $this->updateStockBalanceReserved($reservation->item_id, $reservation->location_id);
            $count++;
        }

        Log::info("Expired {$count} stock reservations");

        return $count;
    }

    /**
     * Get reservation summary for an item.
     */
    public function getReservationSummary(int $itemId): array
    {
        $balances = StockBalance::where('item_id', $itemId)
            ->with('location')
            ->get();

        $reservations = StockReservation::where('item_id', $itemId)
            ->where('status', 'active')
            ->get();

        return [
            'item_id' => $itemId,
            'item_code' => ItemMaster::find($itemId)?->item_code,
            'locations' => $balances->map(fn ($b) => [
                'location_id' => $b->location_id,
                'location_name' => $b->location?->name ?? 'Unknown',
                'on_hand' => (float) $b->quantity_on_hand,
                'reserved' => (float) $b->quantity_reserved,
                'available' => max(0, (float) $b->quantity_on_hand - (float) $b->quantity_reserved),
            ]),
            'total_on_hand' => $balances->sum('quantity_on_hand'),
            'total_reserved' => $balances->sum('quantity_reserved'),
            'total_available' => $this->getTotalAvailableStock($itemId),
            'active_reservations' => $reservations->count(),
        ];
    }

    /**
     * Update reserved quantity on stock_balances.
     */
    private function updateStockBalanceReserved(int $itemId, ?int $locationId): void
    {
        if ($locationId === null) {
            // Update all locations for this item
            $locations = StockBalance::where('item_id', $itemId)
                ->pluck('location_id');
            
            foreach ($locations as $locId) {
                $this->updateLocationReserved($itemId, $locId);
            }
        } else {
            $this->updateLocationReserved($itemId, $locationId);
        }
    }

    /**
     * Update reserved quantity for a specific item/location.
     */
    private function updateLocationReserved(int $itemId, int $locationId): void
    {
        $totalReserved = StockReservation::where('item_id', $itemId)
            ->where('location_id', $locationId)
            ->where('status', 'active')
            ->sum('quantity_reserved');

        StockBalance::updateOrCreate(
            [
                'item_id' => $itemId,
                'location_id' => $locationId,
            ],
            ['quantity_reserved' => $totalReserved]
        );
    }
}
