<?php

declare(strict_types=1);

namespace App\Http\Resources\Production;

use App\Domains\Production\Models\CombinedDeliverySchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CombinedDeliveryScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'cds_reference' => $this->cds_reference,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'target_delivery_date' => $this->target_delivery_date?->toDateString(),
            'actual_delivery_date' => $this->actual_delivery_date?->toDateString(),
            'delivery_address' => $this->delivery_address,
            'delivery_instructions' => $this->delivery_instructions,
            'total_items' => $this->total_items,
            'ready_items' => $this->ready_items,
            'missing_items' => $this->missing_items,
            'progress_percentage' => $this->total_items > 0
                ? round(($this->ready_items / $this->total_items) * 100, 1)
                : 0,
            'item_status_summary' => $this->item_status_summary,
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'is_ready' => $this->isFullyDeliverable(),
            'can_dispatch' => in_array($this->status, [
                CombinedDeliverySchedule::STATUS_READY,
                CombinedDeliverySchedule::STATUS_PARTIALLY_READY,
            ], true),
            'client_order' => $this->whenLoaded('clientOrder', fn () => [
                'id' => $this->clientOrder->id,
                'order_reference' => $this->clientOrder->order_reference,
                'total_amount' => $this->clientOrder->getFormattedTotal(),
            ]),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),
            'item_schedules' => $this->whenLoaded('itemSchedules', fn () => $this->itemSchedules->map(fn ($schedule) => [
                'id' => $schedule->id,
                'ulid' => $schedule->ulid,
                'ds_reference' => $schedule->ds_reference,
                'product_name' => $schedule->productItem?->name,
                'qty_ordered' => $schedule->qty_ordered,
                'status' => $schedule->status,
                'production_orders' => $schedule->whenLoaded('productionOrders', fn () => $schedule->productionOrders->map(fn ($po) => [
                    'id' => $po->id,
                    'po_reference' => $po->po_reference,
                    'status' => $po->status,
                    'progress_pct' => $po->progress_pct,
                ])
                ),
            ])
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'planning' => 'Planning',
            'ready' => 'Ready for Delivery',
            'partially_ready' => 'Partially Ready',
            'dispatched' => 'Dispatched',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
