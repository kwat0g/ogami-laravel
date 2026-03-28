<?php

declare(strict_types=1);

namespace App\Listeners\Production;

use App\Domains\Production\Services\ProductionCostPostingService;
use App\Events\Production\ProductionOrderCompleted;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-post production cost variance to GL when a production order completes.
 *
 * A4: Previously this required a manual API call (POST /post-cost).
 * Now fires automatically on ProductionOrderCompleted event, matching
 * the same pattern as payroll auto-post on PayrollRunObserver.
 *
 * Controlled by system_setting 'automation.production_completed.auto_post_cost_gl'.
 * When disabled, the manual endpoint still works as a fallback.
 */
final class AutoPostProductionCostOnComplete
{
    public function __construct(
        private readonly ProductionCostPostingService $costPostingService,
    ) {}

    public function handle(ProductionOrderCompleted $event): void
    {
        $order = $event->order;

        // Check if automation is enabled
        $enabled = (bool) (DB::table('system_settings')
            ->where('key', 'automation.production_completed.auto_post_cost_gl')
            ->value('value') ?? true);

        if (! $enabled) {
            return;
        }

        // Only post for completed orders with a BOM (cost requires BOM)
        if ($order->status !== 'completed' || $order->bom_id === null) {
            return;
        }

        try {
            // Use system user (created_by) as the actor for auto-posting
            $actor = User::find($order->created_by_id) ?? User::first();

            if ($actor === null) {
                Log::warning('[Production Cost Auto-Post] No user found for auto-posting', [
                    'production_order_id' => $order->id,
                ]);

                return;
            }

            $result = $this->costPostingService->postCostVariance($order, $actor);

            Log::info('[Production Cost Auto-Post] GL entry posted for production order', [
                'production_order_id' => $order->id,
                'journal_entry_id' => $result['journal_entry_id'] ?? null,
                'variance_centavos' => $result['variance']['variance_centavos'] ?? 0,
                'favorable' => $result['variance']['favorable'] ?? true,
            ]);
        } catch (\Throwable $e) {
            // Don't fail the completion flow if cost posting fails
            Log::warning('[Production Cost Auto-Post] Failed to post cost variance', [
                'production_order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
