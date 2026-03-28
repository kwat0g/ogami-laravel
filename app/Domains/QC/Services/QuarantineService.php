<?php

declare(strict_types=1);

namespace App\Domains\QC\Services;

use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Services\StockService;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * QC Quarantine Service — Item 36.
 *
 * Manages stock quarantine for items that fail or are pending QC inspection.
 * Stock in quarantine is not available for production or delivery.
 *
 * Workflow:
 *   1. GR with requires_iqc items → stock enters quarantine location
 *   2. QC inspection performed
 *   3. Pass → release from quarantine to main stock location
 *   4. Fail → return to vendor or scrap (stock leaves quarantine)
 *
 * Quarantine is implemented as a designated warehouse location type.
 */
final class QuarantineService implements ServiceContract
{
    private const QUARANTINE_LOCATION_CODE = 'QC-HOLD';

    /**
     * Place stock into quarantine after receipt of IQC-required items.
     *
     * @return array{quarantine_entry_id: int, item_id: int, quantity: float, location: string}
     */
    public function quarantine(
        int $itemId,
        int $sourceLocationId,
        float $quantity,
        string $referenceType,
        int $referenceId,
        User $actor,
        ?string $reason = null,
    ): array {
        $quarantineLocation = $this->getOrCreateQuarantineLocation();

        return DB::transaction(function () use ($itemId, $sourceLocationId, $quantity, $referenceType, $referenceId, $actor, $quarantineLocation, $reason): array {
            // Move stock from source to quarantine location
            // Decrease source
            $sourceBalance = StockBalance::firstOrCreate(
                ['item_id' => $itemId, 'location_id' => $sourceLocationId],
                ['quantity_on_hand' => 0],
            );
            $sourceBalance->decrement('quantity_on_hand', $quantity);

            // Increase quarantine
            $quarantineBalance = StockBalance::firstOrCreate(
                ['item_id' => $itemId, 'location_id' => $quarantineLocation->id],
                ['quantity_on_hand' => 0],
            );
            $quarantineBalance->increment('quantity_on_hand', $quantity);

            // Log the quarantine entry
            $entryId = DB::table('stock_quarantine_log')->insertGetId([
                'item_id' => $itemId,
                'quantity' => $quantity,
                'quarantine_location_id' => $quarantineLocation->id,
                'source_location_id' => $sourceLocationId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason' => $reason ?? 'Pending IQC inspection',
                'status' => 'quarantined',
                'quarantined_by_id' => $actor->id,
                'quarantined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'quarantine_entry_id' => $entryId,
                'item_id' => $itemId,
                'quantity' => $quantity,
                'location' => $quarantineLocation->name,
            ];
        });
    }

    /**
     * Release stock from quarantine after QC pass.
     */
    public function release(
        int $quarantineEntryId,
        int $targetLocationId,
        User $actor,
    ): array {
        $entry = DB::table('stock_quarantine_log')->find($quarantineEntryId);

        if ($entry === null) {
            throw new DomainException('Quarantine entry not found.', 'QC_QUARANTINE_NOT_FOUND', 404);
        }

        if ($entry->status !== 'quarantined') {
            throw new DomainException("Cannot release entry in status '{$entry->status}'.", 'QC_QUARANTINE_INVALID_STATUS', 422);
        }

        return DB::transaction(function () use ($entry, $targetLocationId, $actor, $quarantineEntryId): array {
            $quantity = (float) $entry->quantity;

            // Move from quarantine to target
            $quarantineBalance = StockBalance::where('item_id', $entry->item_id)
                ->where('location_id', $entry->quarantine_location_id)
                ->first();

            if ($quarantineBalance) {
                $quarantineBalance->decrement('quantity_on_hand', $quantity);
            }

            $targetBalance = StockBalance::firstOrCreate(
                ['item_id' => $entry->item_id, 'location_id' => $targetLocationId],
                ['quantity_on_hand' => 0],
            );
            $targetBalance->increment('quantity_on_hand', $quantity);

            DB::table('stock_quarantine_log')
                ->where('id', $quarantineEntryId)
                ->update([
                    'status' => 'released',
                    'released_by_id' => $actor->id,
                    'released_at' => now(),
                    'target_location_id' => $targetLocationId,
                    'updated_at' => now(),
                ]);

            return [
                'quarantine_entry_id' => $quarantineEntryId,
                'item_id' => $entry->item_id,
                'quantity' => $quantity,
                'action' => 'released',
            ];
        });
    }

    /**
     * Reject quarantined stock — return to vendor or scrap.
     */
    public function reject(
        int $quarantineEntryId,
        string $disposition, // 'return_to_vendor' | 'scrap'
        User $actor,
        ?string $remarks = null,
    ): array {
        $entry = DB::table('stock_quarantine_log')->find($quarantineEntryId);

        if ($entry === null || $entry->status !== 'quarantined') {
            throw new DomainException('Invalid quarantine entry.', 'QC_QUARANTINE_INVALID', 422);
        }

        return DB::transaction(function () use ($entry, $disposition, $actor, $quarantineEntryId, $remarks): array {
            // Remove from quarantine balance
            StockBalance::where('item_id', $entry->item_id)
                ->where('location_id', $entry->quarantine_location_id)
                ->decrement('quantity_on_hand', (float) $entry->quantity);

            DB::table('stock_quarantine_log')
                ->where('id', $quarantineEntryId)
                ->update([
                    'status' => $disposition,
                    'released_by_id' => $actor->id,
                    'released_at' => now(),
                    'remarks' => $remarks,
                    'updated_at' => now(),
                ]);

            return [
                'quarantine_entry_id' => $quarantineEntryId,
                'item_id' => $entry->item_id,
                'quantity' => (float) $entry->quantity,
                'action' => $disposition,
            ];
        });
    }

    /**
     * Get all items currently in quarantine.
     */
    public function currentQuarantine(): Collection
    {
        return collect(DB::table('stock_quarantine_log as q')
            ->join('item_masters as im', 'q.item_id', '=', 'im.id')
            ->where('q.status', 'quarantined')
            ->select('q.*', 'im.item_code', 'im.name as item_name')
            ->orderBy('q.quarantined_at')
            ->get()
            ->map(fn ($row) => [
                'quarantine_entry_id' => $row->id,
                'item_id' => $row->item_id,
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'quantity' => (float) $row->quantity,
                'reason' => $row->reason,
                'quarantined_at' => $row->quarantined_at,
                'days_in_quarantine' => (int) now()->diffInDays($row->quarantined_at),
            ]));
    }

    private function getOrCreateQuarantineLocation(): object
    {
        $location = DB::table('warehouse_locations')
            ->where('code', self::QUARANTINE_LOCATION_CODE)
            ->first();

        if ($location === null) {
            $id = DB::table('warehouse_locations')->insertGetId([
                'code' => self::QUARANTINE_LOCATION_CODE,
                'name' => 'QC Quarantine Hold',
                'description' => 'Stock pending QC inspection — not available for production or delivery',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $location = DB::table('warehouse_locations')->find($id);
        }

        return $location;
    }
}
