<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\Inventory\LowStockNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Checks item stock balances against reorder points and generates alerts
 * for items below their reorder threshold.
 * Designed to run daily via scheduler.
 */
final class CheckReorderPointsCommand extends Command
{
    protected $signature = 'inventory:check-reorder-points';

    protected $description = 'Check stock balances against reorder points and generate low stock notifications';

    public function handle(): int
    {
        // Get items with reorder_point set and current stock below threshold
        $lowStockItems = DB::table('item_masters')
            ->leftJoin(
                DB::raw('(SELECT item_id, SUM(quantity) as total_qty FROM stock_ledger_entries GROUP BY item_id) sle'),
                'item_masters.id', '=', 'sle.item_id'
            )
            ->whereNotNull('item_masters.reorder_point')
            ->where('item_masters.reorder_point', '>', 0)
            ->where('item_masters.is_active', true)
            ->whereRaw('COALESCE(sle.total_qty, 0) <= item_masters.reorder_point')
            ->select(
                'item_masters.id',
                'item_masters.item_code',
                'item_masters.name',
                'item_masters.reorder_point',
                DB::raw('COALESCE(sle.total_qty, 0) as current_stock'),
            )
            ->get();

        $this->info("Found {$lowStockItems->count()} items below reorder point.");

        // Notify inventory managers
        if ($lowStockItems->isNotEmpty()) {
            $users = User::role(['admin', 'manager', 'officer'])
                ->whereHas('roles.permissions', fn ($q) => $q->where('name', 'like', 'inventory.%'))
                ->get();

            foreach ($users as $user) {
                $user->notify(new LowStockNotification($lowStockItems->count(), $lowStockItems->take(10)->toArray()));
            }

            $this->info("Notified {$users->count()} inventory users.");
        }

        return self::SUCCESS;
    }
}
