<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Inventory\Models\StockLedger;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Production\Models\ProductionOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockLedger */
final class StockLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'location_id' => $this->location_id,
            'lot_batch_id' => $this->lot_batch_id,
            'transaction_type' => $this->transaction_type,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference_label' => $this->resolveReferenceLabel(),
            'reference_ulid' => $this->resolveReferenceUlid(),
            'quantity' => $this->quantity,
            'balance_after' => $this->balance_after,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toIso8601String(),
            'item' => $this->whenLoaded('item', fn () => [
                'id' => $this->item->id,
                'item_code' => $this->item->item_code,
                'name' => $this->item->name,
            ]),
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'code' => $this->location->code,
                'name' => $this->location->name,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
        ];
    }

    private function resolveReferenceLabel(): ?string
    {
        if ($this->reference_type === null || $this->reference_id === null) {
            return null;
        }

        return match ($this->reference_type) {
            'goods_receipts' => GoodsReceipt::find($this->reference_id)?->gr_reference,
            'material_requisitions' => MaterialRequisition::find($this->reference_id)?->mr_reference,
            'production_orders' => ProductionOrder::find($this->reference_id)?->po_reference,
            default => "{$this->reference_type}#{$this->reference_id}",
        };
    }

    private function resolveReferenceUlid(): ?string
    {
        if ($this->reference_type === null || $this->reference_id === null) {
            return null;
        }

        return match ($this->reference_type) {
            'goods_receipts' => GoodsReceipt::find($this->reference_id)?->ulid,
            'material_requisitions' => MaterialRequisition::find($this->reference_id)?->ulid,
            'production_orders' => ProductionOrder::find($this->reference_id)?->ulid,
            default => null,
        };
    }
}
