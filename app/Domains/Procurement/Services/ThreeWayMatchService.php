<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Events\Procurement\ThreeWayMatchPassed;
use App\Models\User;
use App\Notifications\Procurement\PartialReceiptDiscrepancyNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Three-Way Match Service.
 *
 * Validates that PR (approved) → PO (sent) → GR (confirmed) quantities reconcile.
 * On success:
 *   1. Updates PO item received quantities.
 *   2. Transitions PO to partially_received or fully_received.
 *   3. Fires ThreeWayMatchPassed event — a listener auto-creates the AP invoice draft.
 *
 * SOD-009 still applies when the Accounting Officer submits the auto-created invoice.
 */
final class ThreeWayMatchService implements ServiceContract
{
    public function runMatch(GoodsReceipt $gr): bool
    {
        $po = $gr->purchaseOrder()->with(['purchaseRequest', 'items'])->firstOrFail();
        $pr = $po->purchaseRequest;

        // Validate PR is approved (if PO was created from a PR)
        // After PO creation the PR transitions to converted_to_po — both statuses are valid for 3WM
        if ($pr !== null && ! in_array($pr->status, ['approved', 'converted_to_po'], true)) {
            throw new DomainException(
                message: "Three-way match failed: Purchase Request #{$pr->pr_reference} is not in an approved status (current: {$pr->status}).",
                errorCode: 'TWM_PR_NOT_APPROVED',
                httpStatus: 422,
            );
        }

        // Validate PO is in a receivable status
        if (! in_array($po->status, ['acknowledged', 'in_transit', 'delivered', 'partially_received'], true)) {
            throw new DomainException(
                message: "Three-way match failed: Purchase Order #{$po->po_reference} is not in a receivable status (current: {$po->status}).",
                errorCode: 'TWM_PO_NOT_RECEIVABLE',
                httpStatus: 422,
            );
        }

        DB::transaction(function () use ($gr, $po): void {
            // Update PO item received quantities
            foreach ($gr->items as $grItem) {
                $poItem = $grItem->poItem;
                $newReceived = (float) $poItem->quantity_received + (float) $grItem->quantity_received;

                if ($newReceived > $poItem->effectiveQuantity()) {
                    throw new DomainException(
                        message: "Three-way match: received quantity ({$newReceived}) would exceed agreed quantity ({$poItem->effectiveQuantity()}) for item '{$poItem->item_description}'.",
                        errorCode: 'TWM_QTY_OVERFLOW',
                        httpStatus: 422,
                    );
                }

                $poItem->update(['quantity_received' => $newReceived]);
            }

            // Refresh PO items to get DB-computed quantity_pending
            $po->load('items');

            // Determine new PO status
            $allReceived = $po->items->every(
                fn ($item) => (float) $item->quantity_pending <= 0.0
            );

            $po->update(['status' => $allReceived ? 'fully_received' : 'partially_received']);

            // Mark GR as matched
            $gr->update(['three_way_match_passed' => true]);
        });

        // Fire event — listener will auto-create the AP invoice draft
        // Removed DB::afterCommit to ensure it fires in tests (sync queue handles it fine)
        $freshGr = $gr->fresh();
        event(new ThreeWayMatchPassed($freshGr));

        // Notify purchasing officers when delivery is partial (items still pending)
        $po->refresh()->load('items');
        if ($po->status === 'partially_received') {
            $pendingItems = $po->items
                ->filter(fn ($item) => (float) $item->quantity_pending > 0)
                ->map(fn ($item) => [
                    'description' => $item->item_description,
                    'ordered_qty' => (float) $item->quantity_ordered,
                    'received_qty' => (float) $item->quantity_received,
                    'pending_qty' => (float) $item->quantity_pending,
                ])
                ->values()
                ->all();

            if (! empty($pendingItems)) {
                $notification = PartialReceiptDiscrepancyNotification::fromModels($freshGr, $po, $pendingItems);

                // Notify all users with procurement view permission
                User::permission('procurement.purchase-order.view')
                    ->each(fn (User $u) => $u->notify($notification));
            }
        }

        return true;
    }
}
