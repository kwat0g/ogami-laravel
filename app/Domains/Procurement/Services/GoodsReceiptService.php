<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GoodsReceiptService implements ServiceContract
{
    public function __construct(
        private readonly ThreeWayMatchService $threeWayMatchService,
        private readonly \App\Domains\AP\Services\InvoiceAutoDraftService $invoiceAutoDraftService,
    ) {}

    // ── Store (draft) ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
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
                'gr_reference' => $reference,
                'purchase_order_id' => $po->id,
                'received_by_id' => $actor->id,
                'received_date' => $receivedDate,
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'condition_notes' => $data['condition_notes'] ?? null,
                'status' => 'draft',
                'three_way_match_passed' => false,
                'ap_invoice_created' => false,
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

                // item_master_id comes directly from the PO item FK — no name-matching needed
                GoodsReceiptItem::create([
                    'goods_receipt_id' => $gr->id,
                    'po_item_id' => $item['po_item_id'],
                    'item_master_id' => $poItem->item_master_id,
                    'quantity_received' => $item['quantity_received'],
                    'unit_of_measure' => $item['unit_of_measure'],
                    'condition' => $item['condition'] ?? 'good',
                    'remarks' => $item['remarks'] ?? null,
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
            $this->resolveItemMasters($gr);

            $gr->update([
                'status' => 'confirmed',
                'confirmed_by_id' => $actor->id,
                'confirmed_at' => now(),
            ]);

            $this->threeWayMatchService->runMatch($gr->refresh());

            // Auto-draft AP invoice from GR + PO data (creates as 'draft' status)
            try {
                $this->invoiceAutoDraftService->createFromGoodsReceipt($gr->refresh());
            } catch (\Throwable $e) {
                // Don't fail the GR confirmation if auto-draft fails — log and continue
                \Illuminate\Support\Facades\Log::warning('[GR Confirm] AP invoice auto-draft failed', [
                    'gr_id' => $gr->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $gr->refresh();
        });
    }

    // ── Reject ───────────────────────────────────────────────────────────────

    /**
     * Reject a draft GR (wrong / damaged goods before confirmation).
     */
    public function reject(GoodsReceipt $gr, User $actor, string $reason): GoodsReceipt
    {
        if ($gr->status !== 'draft') {
            throw new DomainException(
                message: "Only draft GRs can be rejected. Current status: '{$gr->status}'.",
                errorCode: 'GR_NOT_DRAFT',
                httpStatus: 422,
            );
        }

        $gr->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'rejected_by_id' => $actor->id,
            'rejected_at' => now(),
        ]);

        return $gr->refresh();
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /**
     * For each GR item with null item_master_id, attempt to match an existing
     * ItemMaster by name (case-insensitive), or auto-create one from the PO item.
     */
    private function resolveItemMasters(GoodsReceipt $gr): void
    {
        $fallbackCategoryId = ItemCategory::query()->value('id');

        foreach ($gr->items as $grItem) {
            if ($grItem->item_master_id !== null) {
                continue;
            }

            $poItem = $grItem->poItem;
            if (! $poItem) {
                continue;
            }

            $name = trim($poItem->item_description);

            $existing = ItemMaster::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

            if ($existing) {
                $grItem->update(['item_master_id' => $existing->id]);
                $poItem->update(['item_master_id' => $existing->id]);

                continue;
            }

            $slug = strtoupper(substr(Str::slug($name), 0, 20));
            $code = 'AUTO-'.$slug.'-'.strtoupper(Str::random(4));

            $master = ItemMaster::create([
                'item_code' => $code,
                'name' => $name,
                'unit_of_measure' => $poItem->unit_of_measure,
                'type' => 'raw_material',
                'is_active' => true,
                'category_id' => $fallbackCategoryId,
            ]);

            $grItem->update(['item_master_id' => $master->id]);
            $poItem->update(['item_master_id' => $master->id]);
        }
    }

    private function generateReference(): string
    {
        $seq = DB::selectOne('SELECT NEXTVAL(\'goods_receipt_seq\') AS val');
        $num = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);

        return 'GR-'.now()->format('Y-m').'-'.$num;
    }
}
