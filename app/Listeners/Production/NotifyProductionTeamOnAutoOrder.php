<?php

declare(strict_types=1);

namespace App\Listeners\Production;

use App\Events\Production\ProductionOrderAutoCreated;
use App\Models\User;
use App\Notifications\Production\ProductionOrderAutoCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listens for ProductionOrderAutoCreated events and notifies
 * production team members (users with production.orders.view permission).
 */
final class NotifyProductionTeamOnAutoOrder implements ShouldQueue
{
    public function handle(ProductionOrderAutoCreated $event): void
    {
        $notification = ProductionOrderAutoCreatedNotification::fromEvent(
            $event->productionOrder,
            $event->clientOrder,
        );

        $productionUsers = User::permission('production.orders.view')->get();

        if ($productionUsers->isEmpty()) {
            Log::info("ProductionOrderAutoCreated: No users with production.orders.view permission to notify for PO #{$event->productionOrder->id}");

            return;
        }

        foreach ($productionUsers as $user) {
            $user->notify($notification);
        }

        Log::info("ProductionOrderAutoCreated: Notified {$productionUsers->count()} production team member(s) for PO #{$event->productionOrder->id}");
    }
}
