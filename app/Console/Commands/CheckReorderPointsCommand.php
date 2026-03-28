<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Procurement\Services\PurchaseRequestService;
use App\Models\User;
use App\Notifications\Inventory\LowStockNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Checks item stock balances against reorder points and generates alerts
 * for items below their reorder threshold.
 * Auto-creates draft Purchase Requests for items below reorder point.
 * Designed to run daily via scheduler.
 */
final class CheckReorderPointsCommand extends Command
{
    protected $signature = 'inventory:check-reorder-points
                            {--auto-create-pr : Auto-create draft PRs for low stock items}';

    protected $description = 'Check stock balances against reorder points, generate alerts, and optionally auto-create PRs';

    public function __construct(
        private readonly PurchaseRequestService $prService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $autoCreatePr = $this->option('auto-create-pr');

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
                'item_masters.reorder_qty',
                'item_masters.unit_of_measure',
                DB::raw('COALESCE(sle.total_qty, 0) as current_stock'),
            )
            ->get();

        $this->info("Found {$lowStockItems->count()} items below reorder point.");

        $prCreatedCount = 0;
        $prSkippedCount = 0;

        // Auto-create PRs if enabled
        if ($autoCreatePr && $lowStockItems->isNotEmpty()) {
            $systemUser = User::where('email', config('ogami.system_user_email', 'admin@ogamierp.local'))->first();

            if ($systemUser === null) {
                $this->warn('System user not found. Cannot auto-create PRs.');
                Log::warning('CheckReorderPoints: System user not found, skipping auto-PR creation');
            } else {
                foreach ($lowStockItems as $item) {
                    try {
                        // Check if PR already exists for this item in draft/submitted/noted/checked status
                        $existingPr = $this->prExistsForItem($item->id);

                        if ($existingPr) {
                            $this->line("  → Skipped {$item->item_code}: PR already exists");
                            $prSkippedCount++;

                            continue;
                        }

                        // Create draft PR
                        $this->prService->autoCreateFromLowStock(
                            itemId: $item->id,
                            itemCode: $item->item_code,
                            itemName: $item->name,
                            unitOfMeasure: $item->unit_of_measure,
                            reorderPoint: (float) $item->reorder_point,
                            currentStock: (float) $item->current_stock,
                            reorderQty: (float) ($item->reorder_qty ?? $item->reorder_point * 2),
                            actor: $systemUser,
                        );

                        $this->line("  ✓ Created PR for {$item->item_code}");
                        $prCreatedCount++;
                    } catch (\Throwable $e) {
                        $this->error("  ✗ Failed to create PR for {$item->item_code}: {$e->getMessage()}");
                        Log::error("Auto-PR creation failed for item {$item->item_code}", [
                            'error' => $e->getMessage(),
                            'item_id' => $item->id,
                        ]);
                    }
                }
            }
        }

        // Notify inventory managers — one notification per low-stock item
        if ($lowStockItems->isNotEmpty()) {
            $users = User::role(['admin', 'manager', 'officer'])
                ->whereHas('roles.permissions', fn ($q) => $q->where('name', 'like', 'inventory.%'))
                ->get();

            if ($users->isNotEmpty()) {
                foreach ($lowStockItems->take(10) as $item) {
                    $notif = LowStockNotification::fromModel($item, (float) $item->current_stock);
                    foreach ($users as $user) {
                        $user->notify($notif);
                    }
                }
            }

            $this->info("Notified {$users->count()} inventory users.");
        }

        if ($autoCreatePr) {
            $this->info("Auto-PR Summary: {$prCreatedCount} created, {$prSkippedCount} skipped");
        }

        return self::SUCCESS;
    }

    /**
     * Check if a PR already exists for this item in non-final status.
     * For auto-generated PRs, we check by item code in the description.
     */
    private function prExistsForItem(int $itemId): bool
    {
        // Get item code for searching
        $itemCode = DB::table('item_masters')->where('id', $itemId)->value('item_code');

        if ($itemCode === null) {
            return false;
        }

        // Check by item description containing the item code
        // This covers auto-created PRs (description contains item code)
        return DB::table('purchase_requests')
            ->join('purchase_request_items', 'purchase_requests.id', '=', 'purchase_request_items.purchase_request_id')
            ->where('purchase_request_items.item_description', 'like', "%{$itemCode}%")
            ->whereIn('purchase_requests.status', ['draft', 'pending_review', 'reviewed', 'budget_verified'])
            ->exists();
    }
}
