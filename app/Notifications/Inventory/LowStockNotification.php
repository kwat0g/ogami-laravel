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
        private readonly ItemMaster $item,
        private readonly float $currentBalance,
    ) {
        $this->queue = 'notifications';
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
                $this->item->name,
                $this->item->item_code,
                number_format($this->currentBalance, 2),
                $this->item->unit_of_measure,
                number_format((float) $this->item->reorder_point, 2),
            ),
            'action_url' => '/inventory/items/'.$this->item->ulid,
            'item_id' => $this->item->id,
            'item_code' => $this->item->item_code,
            'balance' => $this->currentBalance,
            'reorder_point' => (float) $this->item->reorder_point,
        ];
    }
}
