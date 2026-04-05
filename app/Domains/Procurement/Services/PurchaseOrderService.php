<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorFulfillmentNote;
use App\Domains\AP\Models\VendorItem;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Models\User;
use App\Notifications\Procurement\PurchaseOrderSentNotification;
use App\Notifications\Procurement\PurchaseOrderSentToVendorNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PurchaseOrderService implements ServiceContract
{
    // ── Auto Create ──────────────────────────────────────────────────────────

    /**
     * Auto-create a PO draft from an approved PR.
     */
    public function createFromApprovedPr(PurchaseRequest $pr): PurchaseOrder
    {
        if ($pr->status !== 'approved') {
            throw new DomainException(
                message: 'A Purchase Order can only be auto-created from an approved Purchase Request.',
                errorCode: 'PO_PR_NOT_APPROVED',
                httpStatus: 422,
            );
        }

        // Check if PR already has a PO linking to it (converted_to_po_id)
        if ($pr->converted_to_po_id !== null) {
            throw new DomainException(
                message: 'This Purchase Request has already been converted to a Purchase Order.',
                errorCode: 'PR_ALREADY_CONVERTED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($pr): PurchaseOrder {
            $reference = $this->generateReference();

            $pr->loadMissing('items');

            $resolvedVendorId = $pr->vendor_id;
            if ($resolvedVendorId === null) {
                $vendorItemIds = $pr->items
                    ->pluck('vendor_item_id')
                    ->filter()
                    ->unique()
                    ->values();

                if ($vendorItemIds->count() === 1) {
                    $resolvedVendorId = VendorItem::query()
                        ->whereKey((int) $vendorItemIds->first())
                        ->value('vendor_id');
                }
            }

            if ($resolvedVendorId === null) {
                throw new DomainException(
                    message: 'Cannot auto-create PO — Vendor is required on the source PR.',
                    errorCode: 'PO_VENDOR_REQUIRED',
                    httpStatus: 422,
                );
            }

            // Try to resolve vendor default payment terms
            $vendor = Vendor::find($resolvedVendorId);
            $paymentTerms = $vendor?->payment_terms ?? null;

            $po = PurchaseOrder::create([
                'ulid' => (string) Str::ulid(),
                'po_reference' => $reference,
                'purchase_request_id' => $pr->id,
                'vendor_id' => $resolvedVendorId,
                'po_date' => now()->toDateString(),
                'delivery_date' => null, // To be filled by Purchasing Officer
                'payment_terms' => $paymentTerms, // Default or null
                'delivery_address' => null,
                'notes' => $pr->notes,
                'status' => 'draft',
                'total_po_amount' => 0, // Trigger will update
                // Keep creator FK valid even when legacy created_by_id is null.
                'created_by_id' => $pr->vp_approved_by_id
                    ?? $pr->requested_by_id
                    ?? $pr->created_by_id
                    ?? User::query()->value('id')
                    ?? 1,
            ]);

            // Pre-load vendor catalog for this vendor (centavos, keyed by normalised item_name)
            $catalogPrices = VendorItem::where('vendor_id', $resolvedVendorId)
                ->where('is_active', true)
                ->get(['id', 'item_name', 'unit_price'])
                ->keyBy(fn ($vi) => mb_strtolower(trim($vi->item_name)));

            $vendorItemsById = VendorItem::where('vendor_id', $resolvedVendorId)
                ->whereIn('id', $pr->items->pluck('vendor_item_id')->filter()->all())
                ->get(['id', 'unit_price'])
                ->keyBy('id');

            foreach ($pr->items as $index => $item) {
                $catalogEntry = $item->vendor_item_id
                    ? $vendorItemsById->get($item->vendor_item_id)
                    : null;

                if ($catalogEntry === null) {
                    $normalised = mb_strtolower(trim($item->item_description));
                    $catalogEntry = $catalogPrices->get($normalised);
                }

                // Use catalog price (centavos → PHP) if an exact match exists; fall back to PR estimate
                $agreedCost = $catalogEntry
                    ? $catalogEntry->unit_price / 100
                    : $item->estimated_unit_cost;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'pr_item_id' => $item->id,
                    'item_master_id' => $item->item_master_id,
                    'item_description' => $item->item_description,
                    'unit_of_measure' => $item->unit_of_measure,
                    'quantity_ordered' => $item->quantity,
                    'agreed_unit_cost' => $agreedCost,
                    'quantity_received' => 0,
                    'line_order' => $index + 1,
                ]);
            }

            // Mark the PR as converted
            $pr->update([
                'status' => 'converted_to_po',
                'converted_to_po_id' => $po->id,
                'converted_at' => now(),
            ]);

            return $po->refresh();
        });
    }

    // ── Store ────────────────────────────────────────────────────────────────

    /**
     * Create a PO from an approved PR.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function store(PurchaseRequest $pr, array $data, array $items, User $actor): PurchaseOrder
    {
        if ($pr->status !== 'approved') {
            throw new DomainException(
                message: 'A Purchase Order can only be created from an approved Purchase Request.',
                errorCode: 'PO_PR_NOT_APPROVED',
                httpStatus: 422,
            );
        }

        $vendor = Vendor::findOrFail($data['vendor_id']);

        if (! $vendor->is_active) {
            throw new DomainException(
                message: "Vendor '{$vendor->name}' is inactive and cannot receive Purchase Orders.",
                errorCode: 'PO_VENDOR_INACTIVE',
                httpStatus: 422,
            );
        }

        $vendor->assertAccredited();

        if (empty($items)) {
            throw new DomainException(
                message: 'A Purchase Order must have at least one line item.',
                errorCode: 'PO_NO_ITEMS',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($pr, $data, $items, $actor): PurchaseOrder {
            $reference = $this->generateReference();

            $po = PurchaseOrder::create([
                'ulid' => (string) Str::ulid(),
                'po_reference' => $reference,
                'purchase_request_id' => $pr->id,
                'vendor_id' => $data['vendor_id'],
                'po_date' => now()->toDateString(),
                'delivery_date' => $data['delivery_date'],
                'payment_terms' => $data['payment_terms'],
                'delivery_address' => $data['delivery_address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'total_po_amount' => 0,
                'created_by_id' => $actor->id,
            ]);

            foreach ($items as $index => $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'pr_item_id' => $item['pr_item_id'] ?? null,
                    'item_master_id' => $item['item_master_id'] ?? null,
                    'item_description' => $item['item_description'],
                    'unit_of_measure' => $item['unit_of_measure'],
                    'quantity_ordered' => $item['quantity_ordered'],
                    'agreed_unit_cost' => $item['agreed_unit_cost'],
                    'quantity_received' => 0,
                    'line_order' => $index + 1,
                ]);
            }

            // Mark the PR as converted so it cannot spawn a second PO
            $pr->update([
                'status' => 'converted_to_po',
                'converted_to_po_id' => $po->id,
                'converted_at' => now(),
            ]);

            return $po->refresh();
        });
    }

    // ── Update ───────────────────────────────────────────────────────────────

    /**
     * Update a draft PO (e.g. set delivery date, payment terms, or adjust items).
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function update(PurchaseOrder $po, array $data, array $items): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new DomainException(
                message: 'Only draft Purchase Orders can be updated.',
                errorCode: 'PO_NOT_DRAFT',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($po, $data, $items): PurchaseOrder {
            $po->update([
                'vendor_id' => $data['vendor_id'] ?? $po->vendor_id,
                'delivery_date' => $data['delivery_date'] ?? $po->delivery_date,
                'payment_terms' => $data['payment_terms'] ?? $po->payment_terms,
                'delivery_address' => $data['delivery_address'] ?? $po->delivery_address,
                'notes' => $data['notes'] ?? $po->notes,
            ]);

            // Re-sync items if provided
            if (! empty($items)) {
                $po->items()->delete();
                foreach ($items as $index => $item) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'pr_item_id' => $item['pr_item_id'] ?? null,
                        'item_master_id' => $item['item_master_id'] ?? null,
                        'item_description' => $item['item_description'],
                        'unit_of_measure' => $item['unit_of_measure'],
                        'quantity_ordered' => $item['quantity_ordered'],
                        'agreed_unit_cost' => $item['agreed_unit_cost'],
                        'quantity_received' => 0,
                        'line_order' => $index + 1,
                    ]);
                }
            }

            return $po->refresh();
        });
    }

    // ── Send ─────────────────────────────────────────────────────────────────

    public function send(PurchaseOrder $po, ?string $deliveryDate = null): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new DomainException(
                message: "Cannot send — PO is in status '{$po->status}'.",
                errorCode: 'PO_NOT_DRAFT',
                httpStatus: 422,
            );
        }

        if ($po->vendor_id === null) {
            throw new DomainException(
                message: 'Cannot send — Vendor is required.',
                errorCode: 'PO_VENDOR_REQUIRED',
                httpStatus: 422,
            );
        }

        // Use provided delivery date or fall back to existing one
        $finalDeliveryDate = $deliveryDate ?? $po->delivery_date;

        if ($finalDeliveryDate === null) {
            throw new DomainException(
                message: 'Cannot send — Delivery Date is required.',
                errorCode: 'PO_DELIVERY_DATE_REQUIRED',
                httpStatus: 422,
            );
        }

        if ($po->payment_terms === null || trim($po->payment_terms) === '') {
            throw new DomainException(
                message: 'Cannot send — Payment Terms are required.',
                errorCode: 'PO_PAYMENT_TERMS_REQUIRED',
                httpStatus: 422,
            );
        }

        $po->update([
            'status' => 'sent',
            'delivery_date' => $finalDeliveryDate,
            'sent_at' => now(),
        ]);

        // 1. Notify warehouse staff (internal)
        $po->loadMissing('vendor');
        $internalNotification = PurchaseOrderSentNotification::fromModel($po);
        User::permission('procurement.goods-receipt.create')
            ->each(fn (User $u) => $u->notify($internalNotification));

        // 2. Notify vendor users (external) via the portal
        // Find users linked to this vendor
        $vendorNotification = PurchaseOrderSentToVendorNotification::fromModel($po);
        User::where('vendor_id', $po->vendor_id)
            ->each(fn (User $u) => $u->notify($vendorNotification));

        return $po->refresh();
    }

    // ── Cancel ───────────────────────────────────────────────────────────────

    public function cancel(PurchaseOrder $po, string $reason): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new DomainException(
                message: 'Only draft Purchase Orders can be cancelled.',
                errorCode: 'PO_CANNOT_CANCEL',
                httpStatus: 422,
            );
        }

        $po->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        return $po->refresh();
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function generateReference(): string
    {
        $seq = DB::selectOne('SELECT NEXTVAL(\'purchase_order_seq\') AS val');
        $num = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);

        return 'PO-'.now()->format('Y-m').'-'.$num;
    }

    // ── Finalize vendor assignment (Phase 4) ─────────────────────────────────

    /**
     * Assign a vendor to an auto-created PO draft and map each PO line
     * to a VendorItem from the vendor's catalog.
     *
     * @param  array<string, mixed>  $poData  vendor_id, delivery_date, payment_terms, etc.
     * @param  list<array<string, mixed>>  $itemUpdates  [{ po_item_id, item_master_id?, vendor_item_id?, agreed_unit_cost }]
     */
    public function finalizeVendorAssignment(PurchaseOrder $po, array $poData, array $itemUpdates, User $actor): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new DomainException(
                message: "Only draft POs can have their vendor assigned (current: '{$po->status}').",
                errorCode: 'PO_NOT_DRAFT',
                httpStatus: 422,
            );
        }

        if ($po->vendor_id !== null) {
            throw new DomainException(
                message: 'This PO already has a vendor assigned.',
                errorCode: 'PO_VENDOR_ALREADY_SET',
                httpStatus: 422,
            );
        }

        $vendor = Vendor::findOrFail($poData['vendor_id']);
        $vendor->assertAccredited();

        return DB::transaction(function () use ($po, $poData, $itemUpdates, $vendor): PurchaseOrder {
            $po->update([
                'vendor_id' => $vendor->id,
                'delivery_date' => $poData['delivery_date'] ?? null,
                'payment_terms' => $poData['payment_terms'] ?? null,
                'delivery_address' => $poData['delivery_address'] ?? null,
                'notes' => $poData['notes'] ?? $po->notes,
            ]);

            foreach ($itemUpdates as $upd) {
                $poItem = PurchaseOrderItem::where('purchase_order_id', $po->id)
                    ->findOrFail($upd['po_item_id']);

                $poItem->update([
                    'item_master_id' => $upd['item_master_id'] ?? $poItem->item_master_id,
                    'agreed_unit_cost' => $upd['agreed_unit_cost'] ?? $poItem->agreed_unit_cost,
                ]);
            }

            return $po->refresh();
        });
    }

    // ── Negotiation ──────────────────────────────────────────────────────────

    /**
     * Vendor acknowledges the PO — agrees to all terms as-is.
     * Status: sent → acknowledged
     */
    public function vendorAcknowledge(PurchaseOrder $po, User $vendorUser, string $notes = ''): PurchaseOrder
    {
        if ($po->status !== 'sent') {
            throw new DomainException(
                message: "Cannot acknowledge — PO is in status '{$po->status}'. Only 'sent' POs can be acknowledged.",
                errorCode: 'PO_CANNOT_ACKNOWLEDGE',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($po, $vendorUser, $notes): PurchaseOrder {
            $po->update([
                'status' => 'acknowledged',
                'vendor_acknowledged_at' => now(),
            ]);

            VendorFulfillmentNote::create([
                'purchase_order_id' => $po->id,
                'vendor_user_id' => $vendorUser->id,
                'note_type' => 'acknowledged',
                'notes' => $notes ?: 'Vendor acknowledged PO — agrees to all terms.',
                'items' => null,
            ]);

            return $po->refresh();
        });
    }

    /**
     * Vendor proposes changes (e.g. reduced qty, new delivery date due to stock shortage).
     * Status: sent → negotiating
     *
     * @param  list<array{po_item_id: int, negotiated_quantity?: float, negotiated_unit_price?: int, vendor_item_notes?: string}>  $itemChanges
     */
    public function vendorProposeChanges(
        PurchaseOrder $po,
        User $vendorUser,
        string $remarks,
        array $itemChanges,
        ?string $proposedDeliveryDate = null,
    ): PurchaseOrder {
        if ($po->status !== 'sent') {
            throw new DomainException(
                message: "Cannot propose changes — PO is in status '{$po->status}'. Only 'sent' POs can be negotiated.",
                errorCode: 'PO_CANNOT_PROPOSE_CHANGES',
                httpStatus: 422,
            );
        }

        if (empty(trim($remarks))) {
            throw new DomainException(
                message: 'Vendor remarks are required when proposing changes.',
                errorCode: 'PO_REMARKS_REQUIRED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($po, $vendorUser, $remarks, $itemChanges, $proposedDeliveryDate): PurchaseOrder {
            $round = ($po->negotiation_round ?? 0) + 1;

            $po->update([
                'status' => 'negotiating',
                'vendor_remarks' => $remarks,
                'negotiation_round' => $round,
                'change_requested_at' => now(),
                'change_reviewed_at' => null,
                'change_reviewed_by_id' => null,
                'change_review_remarks' => null,
                'proposed_delivery_date' => $proposedDeliveryDate,
                'proposed_payment_terms' => null,
            ]);

            // Update proposed quantities / prices on line items
            foreach ($itemChanges as $change) {
                $poItem = PurchaseOrderItem::where('purchase_order_id', $po->id)
                    ->findOrFail($change['po_item_id']);

                if (
                    isset($change['negotiated_quantity'])
                    && (float) $change['negotiated_quantity'] >= (float) $poItem->quantity_ordered
                ) {
                    throw new DomainException(
                        message: "Proposed quantity ({$change['negotiated_quantity']}) must be less than ordered quantity ({$poItem->quantity_ordered}) for '{$poItem->item_description}'. Vendors can only propose reduced quantities.",
                        errorCode: 'PO_NEGOTIATED_QTY_NOT_LESS_THAN_ORDERED',
                        httpStatus: 422,
                    );
                }

                $poItem->update([
                    'negotiated_quantity' => $change['negotiated_quantity'] ?? $poItem->negotiated_quantity,
                    'negotiated_unit_price' => $change['negotiated_unit_price'] ?? $poItem->negotiated_unit_price,
                    'vendor_item_notes' => $change['vendor_item_notes'] ?? $poItem->vendor_item_notes,
                ]);
            }

            // Build items snapshot for the fulfillment note
            $po->load('items');
            $itemsSnapshot = $po->items->map(fn ($i) => [
                'po_item_id' => $i->id,
                'item_description' => $i->item_description,
                'quantity_ordered' => $i->quantity_ordered,
                'negotiated_quantity' => $i->negotiated_quantity,
                'negotiated_unit_price' => $i->negotiated_unit_price,
                'vendor_item_notes' => $i->vendor_item_notes,
            ])->values()->all();

            VendorFulfillmentNote::create([
                'purchase_order_id' => $po->id,
                'vendor_user_id' => $vendorUser->id,
                'note_type' => 'change_requested',
                'notes' => $remarks,
                'items' => $itemsSnapshot,
            ]);

            return $po->refresh();
        });
    }

    /**
     * Purchasing Officer accepts the vendor's proposed changes.
     * Status: negotiating → acknowledged
     */
    /**
     * @return array{po: PurchaseOrder, unmet_items: list<array{description: string, original_qty: float, accepted_qty: float, shortfall: float}>}
     */
    public function officerAcceptChanges(PurchaseOrder $po, User $officer, string $remarks = ''): array
    {
        if ($po->status !== 'negotiating') {
            throw new DomainException(
                message: "Cannot accept changes — PO is in status '{$po->status}'.",
                errorCode: 'PO_NOT_NEGOTIATING',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($po, $officer, $remarks): array {
            // 1. Snapshot original values BEFORE applying changes
            $po->load('items');
            $originalTotal = (float) $po->total_po_amount;
            $originalSnapshot = [
                'delivery_date' => $po->delivery_date,
                'payment_terms' => $po->payment_terms,
                'items' => $po->items->map(fn ($i) => [
                    'id' => $i->id,
                    'item_description' => $i->item_description,
                    'quantity_ordered' => $i->quantity_ordered,
                    'agreed_unit_cost' => $i->agreed_unit_cost,
                ])->values()->all(),
            ];

            // 2. Apply changes to PO items
            $unmetItems = [];
            foreach ($po->items as $item) {
                $originalQty = (float) $item->quantity_ordered;
                $updates = ['vendor_item_notes' => null];

                if ($item->negotiated_quantity !== null) {
                    $updates['quantity_ordered'] = (float) $item->negotiated_quantity;
                    $updates['negotiated_quantity'] = null;

                    if ((float) $item->negotiated_quantity < $originalQty) {
                        $unmetItems[] = [
                            'description' => $item->item_description,
                            'original_qty' => $originalQty,
                            'accepted_qty' => (float) $item->negotiated_quantity,
                            'shortfall' => $originalQty - (float) $item->negotiated_quantity,
                        ];
                    }
                }

                if ($item->negotiated_unit_price !== null) {
                    $updates['agreed_unit_cost'] = $item->negotiated_unit_price;
                    $updates['negotiated_unit_price'] = null;
                }

                $item->update($updates);
            }

            // 3. Apply PO-level changes
            $poUpdates = [
                'status' => 'acknowledged',
                'vendor_acknowledged_at' => now(),
                'change_reviewed_at' => now(),
                'change_reviewed_by_id' => $officer->id,
                'change_review_remarks' => $remarks ?: 'Changes accepted.',
                'proposed_delivery_date' => null,
                'proposed_payment_terms' => null,
            ];

            if ($po->proposed_delivery_date) {
                $poUpdates['delivery_date'] = $po->proposed_delivery_date;
            }
            if ($po->proposed_payment_terms) {
                $poUpdates['payment_terms'] = $po->proposed_payment_terms;
            }

            $po->update($poUpdates);

            // 4. Budget recheck — reload after trigger updates total_po_amount
            $po->refresh();
            $newTotal = (float) $po->total_po_amount;
            if ($newTotal > $originalTotal) {
                $po->update([
                    'requires_budget_recheck' => true,
                    'original_total_po_amount' => $originalTotal,
                ]);
                $po->refresh();
            }

            // 5. Audit trail
            $acceptedSnapshot = [
                'delivery_date' => $po->delivery_date,
                'payment_terms' => $po->payment_terms,
                'items' => $po->items->map(fn ($i) => [
                    'id' => $i->id,
                    'item_description' => $i->item_description,
                    'quantity_ordered' => $i->quantity_ordered,
                    'agreed_unit_cost' => $i->agreed_unit_cost,
                ])->values()->all(),
            ];

            VendorFulfillmentNote::create([
                'purchase_order_id' => $po->id,
                'vendor_user_id' => null,
                'note_type' => 'change_accepted',
                'notes' => $remarks ?: 'Purchasing Officer accepted proposed changes.',
                'items' => [
                    'original' => $originalSnapshot,
                    'accepted' => $acceptedSnapshot,
                    'budget_impact' => $newTotal - $originalTotal,
                ],
            ]);

            return ['po' => $po, 'unmet_items' => $unmetItems];
        });
    }

    /**
     * Purchasing Officer rejects the vendor's proposed changes (PO reverts to sent).
     * Vendor must either acknowledge or propose new changes.
     * Status: negotiating → sent
     */
    public function officerRejectChanges(PurchaseOrder $po, User $officer, string $remarks): PurchaseOrder
    {
        if ($po->status !== 'negotiating') {
            throw new DomainException(
                message: "Cannot reject changes — PO is in status '{$po->status}'.",
                errorCode: 'PO_NOT_NEGOTIATING',
                httpStatus: 422,
            );
        }

        if (empty(trim($remarks))) {
            throw new DomainException(
                message: 'A rejection reason is required.',
                errorCode: 'PO_REJECTION_REASON_REQUIRED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($po, $officer, $remarks): PurchaseOrder {
            // Revert negotiated quantities on items back to ordered quantities
            $po->load('items');
            foreach ($po->items as $item) {
                $item->update([
                    'negotiated_quantity' => null,
                    'negotiated_unit_price' => null,
                    'vendor_item_notes' => null,
                ]);
            }

            $po->update([
                'status' => 'sent',
                'vendor_remarks' => null,
                'change_requested_at' => null,
                'change_reviewed_at' => now(),
                'change_reviewed_by_id' => $officer->id,
                'change_review_remarks' => $remarks,
                'proposed_delivery_date' => null,
                'proposed_payment_terms' => null,
            ]);

            VendorFulfillmentNote::create([
                'purchase_order_id' => $po->id,
                'vendor_user_id' => null,
                'note_type' => 'change_rejected',
                'notes' => $remarks,
                'items' => null,
            ]);

            return $po->refresh();
        });
    }

    // ── Auto-create from approved PR ─────────────────────────────────────────

    /**
     * Called by PurchaseRequestService::vpApprove() after PR is approved.
     *
     * Creates a PO draft with vendor_id = null and item_master_id = null.
     * The Purchasing Officer will later assign the vendor and map items from the vendor catalog.
     */
    public function autoCreateFromPr(PurchaseRequest $pr): PurchaseOrder
    {
        return DB::transaction(function () use ($pr): PurchaseOrder {
            $reference = $this->generateReference();

            $po = PurchaseOrder::create([
                'po_reference' => $reference,
                'purchase_request_id' => $pr->id,
                'vendor_id' => $pr->vendor_id ?? null,  // Auto-assigned from PR
                'po_date' => now()->toDateString(),
                'delivery_date' => null,
                'payment_terms' => null,
                'delivery_address' => null,
                'notes' => "Auto-created from approved PR {$pr->pr_reference}.",
                'status' => 'draft',
                'total_po_amount' => 0,
                'created_by_id' => $pr->vp_approved_by_id,
            ]);

            foreach ($pr->items as $index => $prItem) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'pr_item_id' => $prItem->id,
                    'item_master_id' => null,   // Purchasing Officer maps via vendor catalog
                    'item_description' => $prItem->item_description,
                    'unit_of_measure' => $prItem->unit_of_measure,
                    'quantity_ordered' => $prItem->quantity,
                    'agreed_unit_cost' => $prItem->estimated_unit_cost,
                    'quantity_received' => 0,
                    'line_order' => $index + 1,
                ]);
            }

            // Mark the PR as converted so it cannot spawn a second PO
            $pr->update([
                'status' => 'converted_to_po',
                'converted_to_po_id' => $po->id,
                'converted_at' => now(),
            ]);

            return $po->refresh();
        });
    }
}
