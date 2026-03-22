<?php

declare(strict_types=1);

namespace App\Notifications\Inventory;

use App\Domains\Inventory\Models\ItemMaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * In-app notification sent to purchasing officers when an item stock balance
 * drops at or below its configured reorder_point.
 */
final class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $itemId,
        private readonly string $itemUlid,
        private readonly string $itemName,
        private readonly string $itemCode,
        private readonly string $unitOfMeasure,
        private readonly float $currentBalance,
        private readonly float $reorderPoint,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(ItemMaster $item, float $currentBalance): self
    {
        return new self(
            itemId: $item->id,
            itemUlid: $item->ulid,
            itemName: $item->name,
            itemCode: $item->item_code,
            unitOfMeasure: $item->unit_of_measure,
            currentBalance: $currentBalance,
            reorderPoint: (float) $item->reorder_point,
        );
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inventory.low_stock',
            'title' => 'Low Stock Alert',
            'message' => sprintf(
                'Item "%s" (%s) is low on stock. Current balance: %s %s (reorder point: %s).',
                $this->itemName,
                $this->itemCode,
                number_format($this->currentBalance, 2),
                $this->unitOfMeasure,
                number_format($this->reorderPoint, 2),
            ),
            'action_url' => '/inventory/items/'.$this->itemUlid,
            'item_id' => $this->itemId,
            'item_code' => $this->itemCode,
            'balance' => $this->currentBalance,
            'reorder_point' => $this->reorderPoint,
        ];
    }
}
