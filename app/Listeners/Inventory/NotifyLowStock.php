<?php

declare(strict_types=1);

namespace App\Listeners\Inventory;

use App\Events\Inventory\LowStockDetected;
use App\Models\User;
use App\Notifications\Inventory\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Notifies all active purchasing_officer users when a stock balance drops
 * at or below the item's configured reorder_point.
 *
 * ShouldBeUnique — prevents duplicate alert bursts when multiple rapid issues
 * (e.g. WO completion with many parts) all fire for the same item within 60 s.
 */
final class NotifyLowStock implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $uniqueFor = 60;

    /** Unique per item — one notification per item within the lock window. */
    public function uniqueId(LowStockDetected $event): string
    {
        return 'low-stock-' . $event->item->id;
    }

    public function handle(LowStockDetected $event): void
    {
        $notification = LowStockNotification::fromModel($event->item, $event->currentBalance);

        User::role('purchasing_officer')
            ->where('is_active', true)
            ->each(fn (User $user) => $user->notify($notification));
    }
}
