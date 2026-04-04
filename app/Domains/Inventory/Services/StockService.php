<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\LotBatch;
use App\Domains\Inventory\Models\PhysicalCount;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\StockLedger;
use App\Events\Inventory\LowStockDetected;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class StockService implements ServiceContract
{
    /**
     * Receive stock into a location (from GR, production output, etc.)
     *
     * @param  array<string, mixed>  $payload
     */
    public function receive(
        int $itemId,
        int $locationId,
        float $quantity,
        string $referenceType,
        int $referenceId,
        User $actor,
        ?string $lotNumber = null,
        ?string $receivedFrom = 'vendor',
        ?string $receivedDate = null,
        ?string $remarks = null
    ): StockLedger {
        if ($quantity <= 0) {
            throw new DomainException('Receive quantity must be positive.', 'INV_INVALID_QTY', 422);
        }

        // REC-12: Block stock movements when a physical count is in progress
        $this->assertNoActivePhysicalCount($locationId);

        return DB::transaction(function () use (
            $itemId, $locationId, $quantity, $referenceType, $referenceId,
            $actor, $lotNumber, $receivedFrom, $receivedDate, $remarks
        ): StockLedger {
            $lotBatchId = null;
            if ($lotNumber !== null) {
                $lot = LotBatch::firstOrCreate(
                    ['lot_number' => $lotNumber, 'item_id' => $itemId],
                    [
                        'received_from' => $receivedFrom ?? 'vendor',
                        'received_date' => $receivedDate ?? now()->toDateString(),
                        'quantity_received' => $quantity,
                        'quantity_remaining' => $quantity,
                    ]
                );
                $lot->increment('quantity_remaining', $quantity);
                $lotBatchId = $lot->id;
            }

            // Pessimistic lock to prevent concurrent balance corruption
            $balance = $this->currentBalanceLocked($itemId, $locationId);
            $newBalance = $balance + $quantity;

            $ledger = StockLedger::create([
                'item_id' => $itemId,
                'location_id' => $locationId,
                'lot_batch_id' => $lotBatchId,
                'transaction_type' => $referenceType === 'production_orders' ? 'production_output' : 'goods_receipt',
                'reference_type' => $this->normalizeReferenceType($referenceType),
                'reference_id' => $referenceId,
                'quantity' => $quantity,
                'balance_after' => $newBalance,
                'remarks' => $remarks,
                'created_by_id' => $actor->id,
            ]);

            $this->upsertBalance($itemId, $locationId, $newBalance);

            return $ledger;
        });
    }

    /**
     * Issue stock from a location (for material requisition fulfillment).
     */
    public function issue(
        int $itemId,
        int $locationId,
        float $quantity,
        string $referenceType,
        int $referenceId,
        User $actor,
        ?string $remarks = null
    ): StockLedger {
        if ($quantity <= 0) {
            throw new DomainException('Issue quantity must be positive.', 'INV_INVALID_QTY', 422);
        }

        // REC-12: Block stock movements when a physical count is in progress
        $this->assertNoActivePhysicalCount($locationId);

        return DB::transaction(function () use (
            $itemId, $locationId, $quantity, $referenceType, $referenceId, $actor, $remarks
        ): StockLedger {
            // Pessimistic lock to prevent concurrent balance corruption
            $balance = $this->currentBalanceLocked($itemId, $locationId);

            if ($balance < $quantity) {
                throw new DomainException(
                    "Insufficient stock. Available: {$balance}, Requested: {$quantity}",
                    'INV_INSUFFICIENT_STOCK',
                    422
                );
            }

            $newBalance = $balance - $quantity;

            $ledger = StockLedger::create([
                'item_id' => $itemId,
                'location_id' => $locationId,
                'transaction_type' => 'issue',
                'reference_type' => $this->normalizeReferenceType($referenceType),
                'reference_id' => $referenceId,
                'quantity' => -$quantity,
                'balance_after' => $newBalance,
                'remarks' => $remarks,
                'created_by_id' => $actor->id,
            ]);

            $this->upsertBalance($itemId, $locationId, $newBalance);

            // Fire low-stock alert when balance hits or drops below reorder point
            $item = ItemMaster::find($itemId);
            if ($item !== null && $newBalance <= (float) $item->reorder_point) {
                DB::afterCommit(
                    fn () => event(new LowStockDetected($item, $newBalance, $locationId))
                );
            }

            return $ledger;
        });
    }

    /**
     * Return previously issued stock back to a location (e.g. WO voided with 0 output).
     * Creates a positive 'mrq_return' ledger entry mirroring the original issue.
     */
    public function returnFromMrq(
        int $itemId,
        int $locationId,
        float $quantity,
        int $mrqId,
        User $actor,
        ?string $remarks = null
    ): StockLedger {
        return DB::transaction(function () use ($itemId, $locationId, $quantity, $mrqId, $actor, $remarks): StockLedger {
            $balance = $this->currentBalance($itemId, $locationId);
            $newBalance = $balance + $quantity;

            $ledger = StockLedger::create([
                'item_id' => $itemId,
                'location_id' => $locationId,
                'transaction_type' => 'mrq_return',
                'reference_type' => 'material_requisitions',
                'reference_id' => $mrqId,
                'quantity' => $quantity,
                'balance_after' => $newBalance,
                'remarks' => $remarks ?? 'Returned — WO voided',
                'created_by_id' => $actor->id,
            ]);

            $this->upsertBalance($itemId, $locationId, $newBalance);

            return $ledger;
        });
    }

    /**
     * Adjustments — manager-only direct balance corrections.
     */
    public function adjust(
        int $itemId,
        int $locationId,
        float $adjustedQty,
        User $actor,
        string $remarks
    ): StockLedger {
        return DB::transaction(function () use ($itemId, $locationId, $adjustedQty, $actor, $remarks): StockLedger {
            $balance = $this->currentBalance($itemId, $locationId);
            $difference = $adjustedQty - $balance;
            $newBalance = $adjustedQty;

            $ledger = StockLedger::create([
                'item_id' => $itemId,
                'location_id' => $locationId,
                'transaction_type' => 'adjustment',
                'quantity' => $difference,
                'balance_after' => $newBalance,
                'remarks' => $remarks,
                'created_by_id' => $actor->id,
            ]);

            $this->upsertBalance($itemId, $locationId, $newBalance);

            return $ledger;
        });
    }

    /**
     * Transfer stock between two warehouse locations.
     *
     * Creates an issue ledger entry at the source and a receipt at the destination,
     * both referencing a 'transfer' transaction type.
     */
    public function transfer(
        int $itemId,
        int $fromLocationId,
        int $toLocationId,
        float $quantity,
        User $actor,
        ?string $remarks = null
    ): array {
        if ($quantity <= 0) {
            throw new DomainException('Transfer quantity must be positive.', 'INV_INVALID_QTY', 422);
        }

        if ($fromLocationId === $toLocationId) {
            throw new DomainException('Source and destination locations must be different.', 'INV_SAME_LOCATION', 422);
        }

        return DB::transaction(function () use ($itemId, $fromLocationId, $toLocationId, $quantity, $actor, $remarks): array {
            // Pessimistic lock to prevent concurrent balance corruption
            $fromBalance = $this->currentBalanceLocked($itemId, $fromLocationId);

            if ($fromBalance < $quantity) {
                throw new DomainException(
                    "Insufficient stock at source. Available: {$fromBalance}, Requested: {$quantity}",
                    'INV_INSUFFICIENT_STOCK',
                    422
                );
            }

            $newFromBalance = $fromBalance - $quantity;
            $toBalance = $this->currentBalanceLocked($itemId, $toLocationId);
            $newToBalance = $toBalance + $quantity;

            // Issue from source
            $issueLedger = StockLedger::create([
                'item_id' => $itemId,
                'location_id' => $fromLocationId,
                'transaction_type' => 'transfer_out',
                'reference_type' => 'stock_transfer',
                'reference_id' => 0,
                'quantity' => -$quantity,
                'balance_after' => $newFromBalance,
                'remarks' => $remarks ?? "Transfer to location #{$toLocationId}",
                'created_by_id' => $actor->id,
            ]);

            // Receive at destination
            $receiveLedger = StockLedger::create([
                'item_id' => $itemId,
                'location_id' => $toLocationId,
                'transaction_type' => 'transfer_in',
                'reference_type' => 'stock_transfer',
                'reference_id' => $issueLedger->id,
                'quantity' => $quantity,
                'balance_after' => $newToBalance,
                'remarks' => $remarks ?? "Transfer from location #{$fromLocationId}",
                'created_by_id' => $actor->id,
            ]);

            $this->upsertBalance($itemId, $fromLocationId, $newFromBalance);
            $this->upsertBalance($itemId, $toLocationId, $newToBalance);

            // Fire low-stock alert if source location drops below reorder point
            $item = ItemMaster::find($itemId);
            if ($item !== null && $newFromBalance <= (float) $item->reorder_point) {
                DB::afterCommit(
                    fn () => event(new LowStockDetected($item, $newFromBalance, $fromLocationId))
                );
            }

            return [
                'issue_ledger' => $issueLedger,
                'receive_ledger' => $receiveLedger,
            ];
        });
    }

    /**
     * Normalize stock ledger reference type values to prevent DB overflow.
     */
    private function normalizeReferenceType(string $referenceType): string
    {
        if (class_exists($referenceType) && is_subclass_of($referenceType, Model::class)) {
            $referenceType = (new $referenceType())->getTable();
        }

        return substr($referenceType, 0, 50);
    }

    public function currentBalance(int $itemId, int $locationId): float
    {
        $sb = StockBalance::where('item_id', $itemId)
            ->where('location_id', $locationId)
            ->first();

        return $sb ? (float) $sb->quantity_on_hand : 0.0;
    }

    /**
     * Get current balance with pessimistic lock (FOR UPDATE).
     * Must be called inside a DB::transaction().
     */
    private function currentBalanceLocked(int $itemId, int $locationId): float
    {
        $sb = StockBalance::where('item_id', $itemId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        return $sb ? (float) $sb->quantity_on_hand : 0.0;
    }

    private function upsertBalance(int $itemId, int $locationId, float $newBalance): void
    {
        DB::table('stock_balances')
            ->upsert(
                [['item_id' => $itemId, 'location_id' => $locationId, 'quantity_on_hand' => $newBalance]],
                ['item_id', 'location_id'],
                ['quantity_on_hand'],
            );
    }

    /**
     * REC-12: Block stock movements when a physical count is actively in progress.
     *
     * A physical count snapshot becomes stale if stock movements occur during
     * counting, leading to incorrect variance calculations. This guard prevents
     * any receive/issue operations on locations under active count.
     *
     * @throws DomainException
     */
    private function assertNoActivePhysicalCount(int $locationId): void
    {
        $activeCount = PhysicalCount::where('location_id', $locationId)
            ->whereIn('status', ['in_progress', 'pending_approval'])
            ->first();

        if ($activeCount) {
            throw new DomainException(
                "Cannot modify stock: physical count #{$activeCount->reference_number} is in progress for this warehouse location. "
                . 'Complete or cancel the count before receiving or issuing stock.',
                'INV_LOCATION_LOCKED_FOR_COUNT',
                423,
                ['physical_count_id' => $activeCount->id, 'location_id' => $locationId],
            );
        }
    }
}
