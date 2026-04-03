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
            // Uses effectiveAcceptedQuantity() to account for QC splits:
            // after partial acceptance, only the QC-approved quantity counts.
            foreach ($gr->items as $grItem) {
                $poItem = $grItem->poItem;
                $acceptedQty = $grItem->effectiveAcceptedQuantity();
                $newReceived = (float) $poItem->quantity_received + $acceptedQty;

                // H6 FIX: Validate item-level correlation — ensure the GR line item
                // corresponds to the same ItemMaster as the PO line item. Without
                // this, a vendor could invoice for different items as long as quantities match.
                if ($grItem->item_master_id !== null && $poItem->item_master_id !== null
                    && $grItem->item_master_id !== $poItem->item_master_id) {
                    throw new DomainException(
                        message: "Three-way match: GR item (ItemMaster #{$grItem->item_master_id}) does not match "
                            ."PO item (ItemMaster #{$poItem->item_master_id}) for '{$poItem->item_description}'. "
                            .'Item identity must match between PO and GR.',
                        errorCode: 'TWM_ITEM_MISMATCH',
                        httpStatus: 422,
                    );
                }

                if ($newReceived > $poItem->effectiveQuantity()) {
                    throw new DomainException(
                        message: "Three-way match: received quantity ({$newReceived}) would exceed agreed quantity ({$poItem->effectiveQuantity()}) for item '{$poItem->item_description}'.",
                        errorCode: 'TWM_QTY_OVERFLOW',
                        httpStatus: 422,
                    );
                }

                // Track rejected quantity on PO item (from QC partial acceptance)
                $rejectedQty = (float) ($grItem->quantity_rejected ?? 0);
                $newRejected = (float) ($poItem->quantity_rejected ?? 0) + $rejectedQty;

                $poItem->update([
                    'quantity_received' => $newReceived,
                    'quantity_rejected' => $newRejected,
                ]);
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

        // Fire event after commit so listener-side failures cannot poison the
        // active transaction state used by the caller.
        $freshGr = $gr->fresh();
        DB::afterCommit(fn () => event(new ThreeWayMatchPassed($freshGr)));

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
