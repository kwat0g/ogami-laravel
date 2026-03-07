<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class GoodsReceiptService implements ServiceContract
{
    public function __construct(
        private readonly ThreeWayMatchService $threeWayMatchService,
    ) {}

    // ── Store (draft) ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>       $data
     * @param  list<array<string, mixed>> $items
     */
    public function store(PurchaseOrder $po, array $data, array $items, User $actor): GoodsReceipt
    {
        if (! $po->canReceiveGoods()) {
            throw new DomainException(
                message: "Cannot create a Goods Receipt — PO is in status '{$po->status}'.",
                errorCode: 'GR_PO_NOT_SENT',
                httpStatus: 422,
            );
        }

        if (empty($items)) {
            throw new DomainException(
                message: 'A Goods Receipt must have at least one line item.',
                errorCode: 'GR_NO_ITEMS',
                httpStatus: 422,
            );
        }

        $receivedDate = $data['received_date'] ?? now()->toDateString();

        return DB::transaction(function () use ($po, $data, $items, $actor, $receivedDate): GoodsReceipt {
            $reference = $this->generateReference();

            $gr = GoodsReceipt::create([
                'gr_reference'          => $reference,
                'purchase_order_id'     => $po->id,
                'received_by_id'        => $actor->id,
                'received_date'         => $receivedDate,
                'delivery_note_number'  => $data['delivery_note_number'] ?? null,
                'condition_notes'       => $data['condition_notes'] ?? null,
                'status'                => 'draft',
                'three_way_match_passed' => false,
                'ap_invoice_created'    => false,
            ]);

            foreach ($items as $item) {
                // Validate quantity against PO pending amount
                $poItem = $po->items()->findOrFail($item['po_item_id']);
                if ((float) $item['quantity_received'] > (float) $poItem->quantity_pending) {
                    throw new DomainException(
                        message: "Received quantity ({$item['quantity_received']}) exceeds pending quantity ({$poItem->quantity_pending}) for PO item #{$poItem->id}.",
                        errorCode: 'GR_QTY_EXCEEDS_PENDING',
                        httpStatus: 422,
                    );
                }

                // Auto-resolve item_master_id from PO item description
                // e.g. "PP Resin Natural (RAW-001)" → look up ItemMaster by code RAW-001
                $itemMasterId = $this->resolveItemMasterId($poItem->item_description ?? '');

                GoodsReceiptItem::create([
                    'goods_receipt_id'  => $gr->id,
                    'po_item_id'        => $item['po_item_id'],
                    'item_master_id'    => $itemMasterId,
                    'quantity_received' => $item['quantity_received'],
                    'unit_of_measure'   => $item['unit_of_measure'],
                    'condition'         => $item['condition'] ?? 'good',
                    'remarks'           => $item['remarks'] ?? null,
                ]);
            }

            return $gr->refresh();
        });
    }

    // ── Confirm ──────────────────────────────────────────────────────────────

    /**
     * Confirm a GR draft → triggers three-way match + AP invoice creation.
     */
    public function confirm(GoodsReceipt $gr, User $actor): GoodsReceipt
    {
        if ($gr->status !== 'draft') {
            throw new DomainException(
                message: "GR is already in status '{$gr->status}'.",
                errorCode: 'GR_NOT_DRAFT',
                httpStatus: 422,
            );
        }

        // Validate: items with rejected condition must have remarks
        foreach ($gr->items as $item) {
            if ($item->condition === 'rejected' && empty($item->remarks)) {
                throw new DomainException(
                    message: 'Rejected items must have a remarks explanation.',
                    errorCode: 'GR_REJECTED_NEEDS_REMARKS',
                    httpStatus: 422,
                );
            }
        }

        return DB::transaction(function () use ($gr, $actor): GoodsReceipt {
            $gr->update([
                'status'        => 'confirmed',
                'confirmed_by_id' => $actor->id,
                'confirmed_at'  => now(),
            ]);

            $this->threeWayMatchService->runMatch($gr->refresh());

            return $gr->refresh();
        });
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /**
     * Try to resolve an ItemMaster ID from a free-text description.
     * Handles two formats:
     *   - "PP Resin Natural (RAW-001)"  → extracts code from parentheses
     *   - "RAW-001"                     → direct code match
     */
    private function resolveItemMasterId(string $description): ?int
    {
        // Extract code from parentheses e.g. (RAW-001)
        if (preg_match('/\(([A-Z0-9\-]+)\)/', $description, $matches)) {
            $item = ItemMaster::where('item_code', $matches[1])->first();
            if ($item !== null) {
                return $item->id;
            }
        }

        // Try the whole description as a direct item code
        $item = ItemMaster::where('item_code', trim($description))->first();
        if ($item !== null) {
            return $item->id;
        }

        // Try matching by item name (e.g. "PP Resin Natural" → RAW-001)
        $item = ItemMaster::where('name', trim($description))->first();

        return $item?->id;
    }

    private function generateReference(): string
    {
        $seq = DB::selectOne('SELECT NEXTVAL(\'goods_receipt_seq\') AS val');
        $num = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);

        return 'GR-' . now()->format('Y-m') . '-' . $num;
    }
}
