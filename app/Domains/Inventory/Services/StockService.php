<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\LotBatch;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\StockLedger;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class StockService implements ServiceContract
{
    /**
     * Receive stock into a location (from GR, production output, etc.)
     *
     * @param array<string, mixed> $payload
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

        return DB::transaction(function () use (
            $itemId, $locationId, $quantity, $referenceType, $referenceId,
            $actor, $lotNumber, $receivedFrom, $receivedDate, $remarks
        ): StockLedger {
            $lotBatchId = null;
            if ($lotNumber !== null) {
                $lot = LotBatch::firstOrCreate(
                    ['lot_number' => $lotNumber, 'item_id' => $itemId],
                    [
                        'received_from'     => $receivedFrom ?? 'vendor',
                        'received_date'     => $receivedDate ?? now()->toDateString(),
                        'quantity_received' => $quantity,
                        'quantity_remaining' => $quantity,
                    ]
                );
                $lot->increment('quantity_remaining', $quantity);
                $lotBatchId = $lot->id;
            }

            $balance = $this->currentBalance($itemId, $locationId);
            $newBalance = $balance + $quantity;

            return StockLedger::create([
                'item_id'          => $itemId,
                'location_id'      => $locationId,
                'lot_batch_id'     => $lotBatchId,
                'transaction_type' => $referenceType === 'production_orders' ? 'production_output' : 'goods_receipt',
                'reference_type'   => $referenceType,
                'reference_id'     => $referenceId,
                'quantity'         => $quantity,
                'balance_after'    => $newBalance,
                'remarks'          => $remarks,
                'created_by_id'    => $actor->id,
            ]);
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

        return DB::transaction(function () use (
            $itemId, $locationId, $quantity, $referenceType, $referenceId, $actor, $remarks
        ): StockLedger {
            $balance = $this->currentBalance($itemId, $locationId);

            if ($balance < $quantity) {
                throw new DomainException(
                    "Insufficient stock. Available: {$balance}, Requested: {$quantity}",
                    'INV_INSUFFICIENT_STOCK',
                    422
                );
            }

            $newBalance = $balance - $quantity;

            return StockLedger::create([
                'item_id'          => $itemId,
                'location_id'      => $locationId,
                'transaction_type' => 'issue',
                'reference_type'   => $referenceType,
                'reference_id'     => $referenceId,
                'quantity'         => -$quantity,
                'balance_after'    => $newBalance,
                'remarks'          => $remarks,
                'created_by_id'    => $actor->id,
            ]);
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
            $balance    = $this->currentBalance($itemId, $locationId);
            $difference = $adjustedQty - $balance;
            $newBalance = $adjustedQty;

            return StockLedger::create([
                'item_id'          => $itemId,
                'location_id'      => $locationId,
                'transaction_type' => 'adjustment',
                'quantity'         => $difference,
                'balance_after'    => $newBalance,
                'remarks'          => $remarks,
                'created_by_id'    => $actor->id,
            ]);
        });
    }

    public function currentBalance(int $itemId, int $locationId): float
    {
        $sb = StockBalance::where('item_id', $itemId)
            ->where('location_id', $locationId)
            ->first();

        return $sb ? (float) $sb->quantity_on_hand : 0.0;
    }
}
