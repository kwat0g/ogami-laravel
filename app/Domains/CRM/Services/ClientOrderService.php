<?php

declare(strict_types=1);

namespace App\Domains\CRM\Services;

use App\Domains\AR\Models\Customer;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\CRM\Models\ClientOrderActivity;
use App\Domains\CRM\Models\ClientOrderDeliverySchedule;
use App\Domains\CRM\Models\ClientOrderItem;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Services\StockReservationService;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\CombinedDeliverySchedule;
use App\Domains\Production\Models\DeliverySchedule;
use App\Domains\Production\Models\ProductionOrder;
use App\Events\Production\ProductionOrderAutoCreated;
use App\Models\User;
use App\Notifications\CRM\ClientOrderApprovedNotification;
use App\Notifications\CRM\ClientOrderNegotiatedNotification;
use App\Notifications\CRM\ClientOrderRejectedNotification;
use App\Notifications\CRM\ClientOrderSubmittedNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Client Order Service - Manages client portal ordering workflow
 */
final class ClientOrderService implements ServiceContract
{
    public function __construct(
        private readonly StockReservationService $stockReservationService,
    ) {}

    /**
     * Submit a new order from the client portal
     *
     * @param  array<int, array{item_master_id: int, quantity: float, unit_price_centavos: int, notes?: string}>  $items
     */
    public function submitOrder(
        int $customerId,
        array $items,
        ?string $requestedDate = null,
        ?string $notes = null,
        ?int $submittedByUserId = null
    ): ClientOrder {
        if (empty($items)) {
            throw new DomainException(
                'Order must contain at least one item.',
                'CLIENT_ORDER_EMPTY',
                422
            );
        }

        // ── Pre-transaction checks (credit + item validation) ─────────────────
        $customer = Customer::findOrFail($customerId);

        if (! $customer->is_active) {
            throw new DomainException(
                'Customer account is inactive and cannot place new orders.',
                'CLIENT_ORDER_CUSTOMER_INACTIVE',
                422
            );
        }

        // Validate items and calculate total (outside transaction to avoid long locks)
        $totalCentavos = 0;
        foreach ($items as $item) {
            $itemMaster = ItemMaster::findOrFail($item['item_master_id']);

            if (! $itemMaster->is_active) {
                throw new DomainException(
                    "Item {$itemMaster->item_code} is not available for ordering.",
                    'ITEM_UNAVAILABLE',
                    422
                );
            }

            if ($itemMaster->standard_price_centavos !== null
                && $item['unit_price_centavos'] < $itemMaster->standard_price_centavos) {
                throw new DomainException(
                    "Price for {$itemMaster->name} cannot be below the standard price of ₱"
                        .number_format($itemMaster->standard_price_centavos / 100, 2).'.',
                    'PRICE_BELOW_STANDARD',
                    422
                );
            }

            $totalCentavos += (int) ($item['quantity'] * $item['unit_price_centavos']);
        }

        // Credit limit check (centavos → peso float, VAT not included yet)
        $customer->assertCreditAvailable($totalCentavos / 100);

        return DB::transaction(function () use ($customerId, $items, $requestedDate, $notes, $submittedByUserId, $totalCentavos): ClientOrder {
            $reference = $this->generateReference();

            // Create order
            $order = ClientOrder::create([
                'customer_id' => $customerId,
                'order_reference' => $reference,
                'status' => ClientOrder::STATUS_PENDING,
                'requested_delivery_date' => $requestedDate,
                'total_amount_centavos' => $totalCentavos,
                'client_notes' => $notes,
                'submitted_by' => $submittedByUserId,
                'submitted_at' => now(),
            ]);

            // Create items
            foreach ($items as $index => $item) {
                $itemMaster = ItemMaster::findOrFail($item['item_master_id']);

                ClientOrderItem::create([
                    'client_order_id' => $order->id,
                    'item_master_id' => $item['item_master_id'],
                    'item_description' => $itemMaster->description ?? $itemMaster->name,
                    'quantity' => $item['quantity'],
                    'unit_of_measure' => $itemMaster->unit_of_measure,
                    'unit_price_centavos' => $item['unit_price_centavos'],
                    'line_total_centavos' => (int) ($item['quantity'] * $item['unit_price_centavos']),
                    'line_notes' => $item['notes'] ?? null,
                    'line_order' => $index + 1,
                ]);
            }

            // Log activity
            $this->logActivity($order, ClientOrderActivity::ACTION_SUBMITTED, $submittedByUserId, 'client', [
                'item_count' => count($items),
                'total_amount' => $totalCentavos,
            ]);

            // Notify sales team
            $this->notifySalesTeam($order);

            return $order->load('items.itemMaster', 'customer');
        });
    }

    /**
     * Approve a client order and create delivery schedule
     */
    public function approveOrder(ClientOrder $order, int $reviewerId, ?string $notes = null): ClientOrder
    {
        // SOD-CLIENT-001: Submitter cannot approve
        if ($order->submitted_by === $reviewerId) {
            throw new DomainException(
                'The person who submitted this order cannot approve it. (SOD-CLIENT-001)',
                'SOD_VIOLATION_CLIENT_ORDER',
                403
            );
        }

        // Can approve from pending, negotiating, or client_responded statuses
        $approvableStatuses = [
            ClientOrder::STATUS_PENDING,
            ClientOrder::STATUS_NEGOTIATING,
            ClientOrder::STATUS_CLIENT_RESPONDED,
        ];

        if (! in_array($order->status, $approvableStatuses, true)) {
            throw new DomainException(
                "Cannot approve order in status: {$order->status}",
                'CLIENT_ORDER_CANNOT_APPROVE',
                422
            );
        }

        // ── VP escalation check for high-value orders ──────────────────────────
        $vpThreshold = (int) json_decode(
            DB::table('system_settings')->where('key', 'client_order_vp_threshold_centavos')->value('value') ?? '50000000'
        );

        $reviewer = User::findOrFail($reviewerId);
        if ($order->total_amount_centavos >= $vpThreshold && ! $reviewer->hasRole(['vice_president', 'executive', 'admin', 'super_admin'])) {
            return DB::transaction(function () use ($order, $reviewerId, $notes): ClientOrder {
                $order->update([
                    'status' => ClientOrder::STATUS_VP_PENDING,
                    'internal_notes' => $notes ? $order->internal_notes."\n[Escalated to VP] {$notes}" : $order->internal_notes,
                ]);

                $this->logActivity($order, ClientOrderActivity::ACTION_APPROVED, $reviewerId, 'staff', [
                    'escalated_to_vp' => true,
                    'threshold_centavos' => $order->total_amount_centavos,
                    'notes' => $notes,
                ], $order->status, ClientOrder::STATUS_VP_PENDING);

                // Notify VP users after commit
                DB::afterCommit(function () use ($order): void {
                    User::role(['vice_president', 'executive'])
                        ->each(fn (User $u) => $u->notify(ClientOrderApprovedNotification::fromModel($order)));
                });

                return $order->fresh(['items', 'customer']);
            });
        }

        return DB::transaction(function () use ($order, $reviewerId, $notes): ClientOrder {
            $previousStatus = $order->status;

            // Create delivery schedules for all items
            $deliverySchedules = $this->createDeliverySchedulesFromOrder($order, $reviewerId);
            $primarySchedule = $deliverySchedules[0] ?? null;

            // Auto-create draft ProductionOrders for items with insufficient stock
            $this->checkAndCreateDraftProductionOrders($deliverySchedules, $order, $reviewerId);

            // Update order
            $order->update([
                'status' => ClientOrder::STATUS_APPROVED,
                'delivery_schedule_id' => $primarySchedule?->id,
                'approved_by' => $reviewerId,
                'approved_at' => now(),
                'agreed_delivery_date' => $order->agreed_delivery_date ?? $order->requested_delivery_date,
                'internal_notes' => $notes ? $order->internal_notes."\n[Approval] {$notes}" : $order->internal_notes,
            ]);

            // Log activity with all schedule IDs
            $this->logActivity($order, ClientOrderActivity::ACTION_APPROVED, $reviewerId, 'staff', [
                'previous_status' => $previousStatus,
                'delivery_schedule_ids' => collect($deliverySchedules)->pluck('id')->toArray(),
                'delivery_schedule_count' => count($deliverySchedules),
                'notes' => $notes,
            ], $previousStatus, ClientOrder::STATUS_APPROVED);

            // Notify client after commit (ShouldQueue — safe outside transaction)
            DB::afterCommit(function () use ($order): void {
                $order->customer->notify(ClientOrderApprovedNotification::fromModel($order));
            });

            return $order->fresh(['items', 'customer', 'deliverySchedule']);
        });
    }

    /**
     * VP approves a high-value order that was escalated to vp_pending status
     */
    public function vpApproveOrder(ClientOrder $order, int $vpUserId, ?string $notes = null): ClientOrder
    {
        if ($order->status !== ClientOrder::STATUS_VP_PENDING) {
            throw new DomainException(
                "Cannot VP-approve order in status: {$order->status}",
                'CLIENT_ORDER_NOT_VP_PENDING',
                422
            );
        }

        return DB::transaction(function () use ($order, $vpUserId, $notes): ClientOrder {
            // Create delivery schedules for all items
            $deliverySchedules = $this->createDeliverySchedulesFromOrder($order, $vpUserId);
            $primarySchedule = $deliverySchedules[0] ?? null;

            // Auto-create draft ProductionOrders for items with insufficient stock
            $this->checkAndCreateDraftProductionOrders($deliverySchedules, $order, $vpUserId);

            $order->update([
                'status' => ClientOrder::STATUS_APPROVED,
                'delivery_schedule_id' => $primarySchedule?->id,
                'vp_approved_by' => $vpUserId,
                'vp_approved_at' => now(),
                'approved_at' => now(),
                'agreed_delivery_date' => $order->agreed_delivery_date ?? $order->requested_delivery_date,
                'internal_notes' => $notes ? $order->internal_notes."\n[VP Approval] {$notes}" : $order->internal_notes,
            ]);

            $this->logActivity($order, ClientOrderActivity::ACTION_APPROVED, $vpUserId, 'staff', [
                'vp_approved' => true,
                'delivery_schedule_ids' => collect($deliverySchedules)->pluck('id')->toArray(),
                'notes' => $notes,
            ], ClientOrder::STATUS_VP_PENDING, ClientOrder::STATUS_APPROVED);

            DB::afterCommit(function () use ($order): void {
                $order->customer->notify(ClientOrderApprovedNotification::fromModel($order));
            });

            return $order->fresh(['items', 'customer', 'deliverySchedule']);
        });
    }

    /**
     * Reject a client order with reason
     */
    public function rejectOrder(
        ClientOrder $order,
        string $reason,
        int $reviewerId,
        ?string $notes = null
    ): ClientOrder {
        // Can reject from pending, negotiating, or client_responded
        $rejectableStatuses = [
            ClientOrder::STATUS_PENDING,
            ClientOrder::STATUS_NEGOTIATING,
            ClientOrder::STATUS_CLIENT_RESPONDED,
        ];
        if (! in_array($order->status, $rejectableStatuses, true)) {
            throw new DomainException(
                "Cannot reject order in status: {$order->status}",
                'CLIENT_ORDER_CANNOT_REJECT',
                422
            );
        }

        $previousStatus = $order->status;

        return DB::transaction(function () use ($order, $reason, $reviewerId, $notes, $previousStatus): ClientOrder {
            $order->update([
                'status' => ClientOrder::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'rejected_by' => $reviewerId,
                'rejected_at' => now(),
                'internal_notes' => $notes ? $order->internal_notes."\n[Rejection] {$notes}" : $order->internal_notes,
            ]);

            // Log activity
            $this->logActivity($order, ClientOrderActivity::ACTION_REJECTED, $reviewerId, 'staff', [
                'reason' => $reason,
                'notes' => $notes,
            ], $previousStatus, ClientOrder::STATUS_REJECTED);

            // Notify client after commit
            DB::afterCommit(function () use ($order, $reason, $notes): void {
                $order->customer->notify(ClientOrderRejectedNotification::fromModel($order, $reason, $notes));
            });

            return $order->fresh(['items', 'customer']);
        });
    }

    /**
     * Negotiate order terms - propose changes to client
     */
    public function negotiateOrder(
        ClientOrder $order,
        string $reason,
        array $proposedChanges,
        int $reviewerId,
        ?string $notes = null
    ): ClientOrder {
        if (! $order->canBeNegotiated()) {
            throw new DomainException(
                "Cannot negotiate order in status: {$order->status}",
                'CLIENT_ORDER_CANNOT_NEGOTIATE',
                422
            );
        }

        // Check maximum negotiation rounds
        if ($order->hasReachedMaxNegotiationRounds()) {
            throw new DomainException(
                'Maximum negotiation rounds reached. Please reject or approve the order.',
                'MAX_NEGOTIATION_ROUNDS',
                422
            );
        }

        $previousStatus = $order->status;
        $newRound = $order->negotiation_round + 1;

        return DB::transaction(function () use ($order, $reason, $proposedChanges, $reviewerId, $notes, $previousStatus, $newRound): ClientOrder {
            // Update order
            $order->update([
                'status' => ClientOrder::STATUS_NEGOTIATING,
                'negotiation_reason' => $reason,
                'negotiation_notes' => $notes,
                'negotiation_turn' => ClientOrder::TURN_CLIENT, // Now waiting for client
                'negotiation_round' => $newRound,
                'last_negotiation_by' => ClientOrder::TURN_SALES,
                'last_negotiation_at' => now(),
                'last_proposal' => [
                    'round' => $newRound,
                    'by' => ClientOrder::TURN_SALES,
                    'reason' => $reason,
                    'changes' => $proposedChanges,
                    'notes' => $notes,
                    'proposed_at' => now()->toIso8601String(),
                ],
            ]);

            // Update line items if negotiated quantities/prices provided
            if (! empty($proposedChanges['items'])) {
                foreach ($proposedChanges['items'] as $itemChange) {
                    $item = ClientOrderItem::where('client_order_id', $order->id)
                        ->where('id', $itemChange['item_id'])
                        ->first();

                    if ($item) {
                        $item->update([
                            'negotiated_quantity' => $itemChange['quantity'] ?? null,
                            'negotiated_price_centavos' => isset($itemChange['price'])
                                ? (int) ($itemChange['price'] * 100)
                                : null,
                        ]);
                    }
                }

                // Recalculate order total after item negotiations
                $this->recalculateOrderTotal($order);
            }

            // Update delivery date if proposed
            if (! empty($proposedChanges['delivery_date'])) {
                $order->update(['agreed_delivery_date' => $proposedChanges['delivery_date']]);
            }

            // Log activity
            $this->logActivity($order, ClientOrderActivity::ACTION_NEGOTIATED, $reviewerId, 'staff', [
                'reason' => $reason,
                'proposed_changes' => $proposedChanges,
                'notes' => $notes,
                'round' => $newRound,
            ], $previousStatus, ClientOrder::STATUS_NEGOTIATING);

            // Notify client after commit
            DB::afterCommit(function () use ($order, $reason, $proposedChanges, $notes): void {
                $order->customer->notify(ClientOrderNegotiatedNotification::fromModel($order, $reason, $proposedChanges, $notes));
            });

            return $order->fresh(['items', 'customer', 'activities']);
        });
    }

    /**
     * Client responds to negotiation (accept, counter, or cancel)
     */
    public function clientRespond(
        ClientOrder $order,
        string $response, // 'accept', 'counter', 'cancel'
        ?array $counterProposals = null,
        ?int $clientUserId = null
    ): ClientOrder {
        // Client can respond when it is their turn: pending or negotiating
        $validStatuses = [
            ClientOrder::STATUS_PENDING,
            ClientOrder::STATUS_NEGOTIATING,
        ];

        if (! in_array($order->status, $validStatuses, true)) {
            throw new DomainException(
                'Order is not in a state that allows client response.',
                'CLIENT_ORDER_NOT_RESPONDABLE',
                422
            );
        }

        $previousStatus = $order->status;

        return DB::transaction(function () use ($order, $response, $counterProposals, $clientUserId, $previousStatus): ClientOrder {
            switch ($response) {
                case 'accept':
                    // Move back to pending for sales to approve with negotiated terms
                    $order->update([
                        'status' => ClientOrder::STATUS_PENDING,
                        'requested_delivery_date' => $order->agreed_delivery_date,
                        'negotiation_turn' => null, // Clear turn tracking
                        'last_negotiation_by' => ClientOrder::TURN_CLIENT,
                        'last_negotiation_at' => now(),
                    ]);
                    $newStatus = ClientOrder::STATUS_PENDING;

                    // Recalculate totals after accepting negotiated terms
                    $this->recalculateOrderTotal($order);
                    break;

                case 'counter':
                    // Increment round only when actually countering, not on first response from pending
                    $newRound = $order->negotiation_round + 1;

                    // Update with client's counter-proposals and change status to client_responded
                    $updateData = [
                        'status' => ClientOrder::STATUS_CLIENT_RESPONDED,
                        'negotiation_turn' => ClientOrder::TURN_SALES, // Now waiting for sales
                        'last_negotiation_by' => ClientOrder::TURN_CLIENT,
                        'last_negotiation_at' => now(),
                        'negotiation_round' => $newRound,
                        'last_proposal' => [
                            'round' => $newRound,
                            'by' => ClientOrder::TURN_CLIENT,
                            'changes' => $counterProposals ?? [],
                            'proposed_at' => now()->toIso8601String(),
                        ],
                    ];

                    if (! empty($counterProposals['delivery_date'])) {
                        $updateData['agreed_delivery_date'] = $counterProposals['delivery_date'];
                    }
                    if (! empty($counterProposals['notes'])) {
                        $updateData['client_notes'] = $order->client_notes."\n[Counter-proposal] ".$counterProposals['notes'];
                    }

                    $order->update($updateData);
                    $newStatus = ClientOrder::STATUS_CLIENT_RESPONDED;
                    break;

                case 'cancel':
                    $order->update([
                        'status' => ClientOrder::STATUS_CANCELLED,
                        'negotiation_turn' => null,
                        'last_negotiation_by' => ClientOrder::TURN_CLIENT,
                        'last_negotiation_at' => now(),
                    ]);
                    $newStatus = ClientOrder::STATUS_CANCELLED;
                    break;

                default:
                    throw new DomainException('Invalid response type.', 'INVALID_RESPONSE', 422);
            }

            // Log activity
            $this->logActivity($order, ClientOrderActivity::ACTION_CLIENT_RESPONDED, $clientUserId, 'client', [
                'response' => $response,
                'counter_proposals' => $counterProposals,
                'round' => $order->negotiation_round,
            ], $previousStatus, $newStatus);

            return $order->fresh(['items', 'customer', 'activities']);
        });
    }

    /**
     * Sales responds to client counter-proposal (accept, counter, or reject)
     */
    public function salesRespondToCounter(
        ClientOrder $order,
        string $response, // 'accept', 'counter', 'reject'
        ?array $counterProposals,
        ?string $notes,
        int $reviewerId
    ): ClientOrder {
        if (! $order->isAwaitingSalesResponse()) {
            throw new DomainException(
                'Order is not awaiting sales response. Current status: '.$order->status,
                'CLIENT_ORDER_NOT_AWAITING_SALES',
                422
            );
        }

        // Check maximum negotiation rounds
        if ($order->hasReachedMaxNegotiationRounds()) {
            throw new DomainException(
                'Maximum negotiation rounds reached. Please reject or approve the order.',
                'MAX_NEGOTIATION_ROUNDS',
                422
            );
        }

        $previousStatus = $order->status;
        $newRound = $order->negotiation_round + 1;

        return DB::transaction(function () use ($order, $response, $counterProposals, $notes, $reviewerId, $previousStatus, $newRound): ClientOrder {
            switch ($response) {
                case 'accept':
                    // Accept client's counter, move to pending for final approval
                    $order->update([
                        'status' => ClientOrder::STATUS_PENDING,
                        'negotiation_turn' => null,
                        'last_negotiation_by' => ClientOrder::TURN_SALES,
                        'last_negotiation_at' => now(),
                        'requested_delivery_date' => $order->agreed_delivery_date,
                        'internal_notes' => $notes ? $order->internal_notes."\n[Accepted counter] ".$notes : $order->internal_notes,
                    ]);
                    $newStatus = ClientOrder::STATUS_PENDING;
                    break;

                case 'counter':
                    // Make another counter-proposal back to client
                    $updateData = [
                        'status' => ClientOrder::STATUS_NEGOTIATING,
                        'negotiation_turn' => ClientOrder::TURN_CLIENT,
                        'negotiation_round' => $newRound,
                        'last_negotiation_by' => ClientOrder::TURN_SALES,
                        'last_negotiation_at' => now(),
                        'negotiation_reason' => $counterProposals['reason'] ?? 'counter_proposal',
                        'negotiation_notes' => $notes,
                        'last_proposal' => [
                            'round' => $newRound,
                            'by' => ClientOrder::TURN_SALES,
                            'changes' => $counterProposals ?? [],
                            'notes' => $notes,
                            'proposed_at' => now()->toIso8601String(),
                        ],
                    ];

                    if (! empty($counterProposals['delivery_date'])) {
                        $updateData['agreed_delivery_date'] = $counterProposals['delivery_date'];
                    }
                    if (! empty($counterProposals['items'])) {
                        foreach ($counterProposals['items'] as $itemChange) {
                            $item = ClientOrderItem::where('client_order_id', $order->id)
                                ->where('id', $itemChange['item_id'])
                                ->first();

                            if (! $item) {
                                throw new DomainException(
                                    "Item {$itemChange['item_id']} not found in this order",
                                    'INVALID_ITEM',
                                    422
                                );
                            }

                            $item->update([
                                'negotiated_quantity' => $itemChange['quantity'] ?? null,
                                'negotiated_price_centavos' => isset($itemChange['price'])
                                    ? (int) ($itemChange['price'] * 100)
                                    : null,
                            ]);
                        }

                        // Recalculate order total after item negotiations
                        $this->recalculateOrderTotal($order);
                    }

                    $order->update($updateData);
                    $newStatus = ClientOrder::STATUS_NEGOTIATING;
                    break;

                case 'reject':
                    // Reject client's counter and end negotiation
                    $order->update([
                        'status' => ClientOrder::STATUS_REJECTED,
                        'negotiation_turn' => null,
                        'last_negotiation_by' => ClientOrder::TURN_SALES,
                        'last_negotiation_at' => now(),
                        'rejection_reason' => $counterProposals['reason'] ?? 'negotiation_failed',
                        'rejected_by' => $reviewerId,
                        'rejected_at' => now(),
                        'internal_notes' => $notes ? $order->internal_notes."\n[Rejected counter] ".$notes : $order->internal_notes,
                    ]);
                    $newStatus = ClientOrder::STATUS_REJECTED;
                    break;

                default:
                    throw new DomainException('Invalid response type.', 'INVALID_RESPONSE', 422);
            }

            // Log activity
            $this->logActivity($order, ClientOrderActivity::ACTION_SALES_RESPONDED, $reviewerId, 'staff', [
                'response' => $response,
                'counter_proposals' => $counterProposals,
                'notes' => $notes,
                'round' => $newRound,
            ], $previousStatus, $newStatus);

            return $order->fresh(['items', 'customer', 'activities']);
        });
    }

    /**
     * Cancel a pending order (client initiated)
     */
    public function cancelOrder(ClientOrder $order, ?int $clientUserId = null): ClientOrder
    {
        $cancelableStatuses = [
            ClientOrder::STATUS_PENDING,
            ClientOrder::STATUS_NEGOTIATING,
            ClientOrder::STATUS_CLIENT_RESPONDED,
        ];

        if (! in_array($order->status, $cancelableStatuses, true)) {
            throw new DomainException(
                'Can only cancel orders in pending, negotiating, or client_responded status.',
                'CLIENT_ORDER_CANNOT_CANCEL',
                422
            );
        }

        $previousStatus = $order->status;

        return DB::transaction(function () use ($order, $clientUserId, $previousStatus): ClientOrder {
            $order->update([
                'status' => ClientOrder::STATUS_CANCELLED,
                'negotiation_turn' => null,
                'cancelled_by' => $clientUserId,
                'cancelled_at' => now(),
            ]);

            // Log activity with metadata
            $this->logActivity($order, ClientOrderActivity::ACTION_CANCELLED, $clientUserId, 'client', [
                'cancelled_at' => now()->toIso8601String(),
                'previous_status' => $previousStatus,
            ], $previousStatus, ClientOrder::STATUS_CANCELLED);

            return $order->fresh(['items', 'customer', 'activities']);
        });
    }

    /**
     * Get paginated orders for sales review
     */
    public function getOrdersForReview(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ClientOrder::with(['customer', 'items.itemMaster'])
            ->orderByRaw("CASE status 
                WHEN 'pending' THEN 1 
                WHEN 'negotiating' THEN 2 
                WHEN 'approved' THEN 3 
                ELSE 4 
            END")
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get orders for a specific customer (client portal)
     */
    public function getOrdersForCustomer(int $customerId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return ClientOrder::with(['items.itemMaster'])
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────────

    private function generateReference(): string
    {
        $seq = DB::selectOne("SELECT NEXTVAL('client_order_seq') AS val");
        $num = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);

        return 'CO-'.now()->format('Y').'-'.$num;
    }

    /**
     * Create delivery schedules for all items in the order
     * Each item gets its own schedule for independent tracking
     *
     * @return array<DeliverySchedule> Array of created schedules
     */
    /**
     * Create delivery schedules for all items in the order
     * Each item gets its own schedule for independent tracking
     * Also creates a CombinedDeliverySchedule to group them for delivery
     *
     * @return array<DeliverySchedule> Array of created schedules
     */
    private function createDeliverySchedulesFromOrder(ClientOrder $order, int $userId): array
    {
        $items = $order->items;

        if ($items->isEmpty()) {
            throw new DomainException(
                'Cannot create delivery schedule: order has no items',
                'CLIENT_ORDER_EMPTY',
                422
            );
        }

        // Create ONE delivery schedule for the entire order (multi-item)
        $schedule = DeliverySchedule::create([
            'customer_id' => $order->customer_id,
            'client_order_id' => $order->id,
            'target_delivery_date' => $order->agreed_delivery_date ?? $order->requested_delivery_date ?? now()->addDays(7),
            'type' => 'local',
            'status' => 'open',
            'notes' => "Created from Client Order {$order->order_reference}",
            'delivery_address' => $order->customer->address ?? null,
            'total_items' => $items->count(),
            'ready_items' => 0,
            'missing_items' => $items->count(),
            'created_by_id' => $userId,
        ]);

        // Create delivery schedule items (one per order line)
        foreach ($items as $item) {
            $dsItem = \App\Domains\Production\Models\DeliveryScheduleItem::create([
                'delivery_schedule_id' => $schedule->id,
                'product_item_id' => $item->item_master_id,
                'qty_ordered' => $item->negotiated_quantity ?? $item->quantity,
                'unit_price' => $item->negotiated_price_centavos
                    ? ($item->negotiated_price_centavos / 100)
                    : ($item->unit_price_centavos ? $item->unit_price_centavos / 100 : null),
                'status' => 'pending',
                'notes' => $item->item_description,
            ]);

            // Link schedule item to client order item via pivot
            ClientOrderDeliverySchedule::create([
                'client_order_id' => $order->id,
                'client_order_item_id' => $item->id,
                'delivery_schedule_id' => $schedule->id,
            ]);
        }

        // Update item status summary
        $schedule->updateItemStatusSummary();

        // Also create a CombinedDeliverySchedule for backward compat during transition
        $combinedSchedule = CombinedDeliverySchedule::create([
            'client_order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'cds_reference' => $this->generateCombinedDeliveryReference(),
            'status' => CombinedDeliverySchedule::STATUS_PLANNING,
            'target_delivery_date' => $order->agreed_delivery_date ?? $order->requested_delivery_date ?? now()->addDays(7),
            'delivery_address' => $order->customer->address ?? null,
            'total_items' => $items->count(),
            'ready_items' => 0,
            'missing_items' => $items->count(),
            'created_by_id' => $userId,
        ]);

        // Link the DS to the CDS for backward compat
        $schedule->update(['combined_delivery_schedule_id' => $combinedSchedule->id]);

        return [$schedule];
    }

    /**
     * Generate unique reference for Combined Delivery Schedule
     */
    private function generateCombinedDeliveryReference(): string
    {
        $seq = DB::selectOne("SELECT NEXTVAL('cds_reference_seq') AS val");
        $num = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);

        return 'CDS-'.now()->format('Y').'-'.$num;
    }

    /**
     * @deprecated Use createDeliverySchedulesFromOrder for multi-item support
     */
    private function createDeliveryScheduleFromOrder(ClientOrder $order): DeliverySchedule
    {
        $schedules = $this->createDeliverySchedulesFromOrder($order, 1);

        return $schedules[0];
    }

    /**
     * Recalculate order totals based on negotiated quantities and prices
     * Also updates individual line totals
     */
    private function recalculateOrderTotal(ClientOrder $order): void
    {
        $total = 0;

        foreach ($order->items as $item) {
            $quantity = $item->negotiated_quantity ?? $item->quantity;
            $price = $item->negotiated_price_centavos ?? $item->unit_price_centavos;
            $lineTotal = (int) ($quantity * $price);

            // Update line total if negotiated values exist
            if ($item->negotiated_quantity !== null || $item->negotiated_price_centavos !== null) {
                $item->update(['line_total_centavos' => $lineTotal]);
            }

            $total += $lineTotal;
        }

        $order->update(['total_amount_centavos' => $total]);
    }

    private function logActivity(
        ClientOrder $order,
        string $action,
        ?int $userId,
        string $userType,
        array $metadata = [],
        ?string $fromStatus = null,
        ?string $toStatus = null
    ): void {
        ClientOrderActivity::create([
            'client_order_id' => $order->id,
            'user_id' => $userId,
            'user_type' => $userType,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => $metadata,
        ]);
    }

    private function notifySalesTeam(ClientOrder $order): void
    {
        // Dispatch after transaction commits — all CRM notifications implement ShouldQueue.
        // Only notify users who belong to the Sales department (module_key='sales').
        DB::afterCommit(function () use ($order): void {
            User::permission('sales.order_review')
                ->whereHas('departments', fn ($q) => $q->where('module_key', 'sales'))
                ->each(fn (User $user) => $user->notify(ClientOrderSubmittedNotification::fromModel($order)));
        });
    }

    /**
     * For each delivery schedule, handle stock fulfillment:
     *  - If stock is sufficient: reserve stock and mark schedule as ready (auto-fulfill).
     *  - If stock is partial: reserve available stock, create draft PO for deficit.
     *  - If no stock: create draft PO for full quantity.
     *  - If BOM is missing: log an activity warning on the Client Order.
     *
     * Enhancements over original:
     *  - Gap #1: Auto-fulfill for in-stock items
     *  - Gap #2: Activity log when BOM is missing
     *  - Gap #3 & #4: Target dates calculated from BOM's standard_production_days
     *  - Gap #5: Production team notified via ProductionOrderAutoCreated event
     *  - Gap #6: Stock reservation using StockReservationService (respects existing reservations)
     *  - Gap #7: client_order_id set on auto-created Production Orders
     *
     * @param  array<DeliverySchedule>  $schedules
     */
    private function checkAndCreateDraftProductionOrders(array $schedules, ClientOrder $order, int $userId): void
    {
        foreach ($schedules as $schedule) {
            // Iterate over DS items (each item = one product line)
            $dsItems = $schedule->items()->with('productItem')->get();

            foreach ($dsItems as $dsItem) {
                $availableStock = $this->stockReservationService->getTotalAvailableStock($dsItem->product_item_id);
                $qtyRequired = (float) $dsItem->qty_ordered;

                // ── Gap #1: Auto-fulfill from stock when fully available ─────────
                if ($availableStock >= $qtyRequired) {
                    try {
                        $this->stockReservationService->createReservation(
                            itemId: $dsItem->product_item_id,
                            quantity: $qtyRequired,
                            reservationType: 'delivery_schedule',
                            referenceId: $dsItem->id,
                            referenceType: 'delivery_schedule_items',
                            notes: "Auto-reserved for Client Order {$order->order_reference}",
                        );

                        $dsItem->update(['status' => 'ready']);
                        $schedule->updateItemStatusSummary();
                    } catch (\Throwable $e) {
                        Log::warning("Auto-fulfill reservation failed for DSI #{$dsItem->id}: {$e->getMessage()}");
                    }

                    continue;
                }

                // ── Gap #6: Partial stock — reserve what's available ──────────────
                $deficit = $qtyRequired;
                if ($availableStock > 0) {
                    try {
                        $this->stockReservationService->createReservation(
                            itemId: $dsItem->product_item_id,
                            quantity: $availableStock,
                            reservationType: 'delivery_schedule',
                            referenceId: $dsItem->id,
                            referenceType: 'delivery_schedule_items',
                            notes: "Partial reservation for Client Order {$order->order_reference} ({$availableStock} of {$qtyRequired})",
                        );
                        $deficit = $qtyRequired - $availableStock;
                    } catch (\Throwable $e) {
                        Log::warning("Partial stock reservation failed for DSI #{$dsItem->id}: {$e->getMessage()}");
                    }
                }

                // ── Gap #2: BOM missing alert ────────────────────────────────────
                $bom = BillOfMaterials::where('product_item_id', $dsItem->product_item_id)
                    ->where('is_active', true)
                    ->first();

                if (! $bom) {
                    $itemName = $dsItem->productItem?->name ?? "Item #{$dsItem->product_item_id}";
                    $this->logActivity($order, 'bom_missing', $userId, 'system', [
                        'item_id' => $dsItem->product_item_id,
                        'item_name' => $itemName,
                        'delivery_schedule_item_id' => $dsItem->id,
                        'message' => "No active BOM found for {$itemName} — manual production planning required.",
                    ]);

                    continue;
                }

                // ── Gap #4: Calculate target dates from BOM + delivery date ──────
                $deliveryDate = $schedule->target_delivery_date
                    ? Carbon::parse($schedule->target_delivery_date)
                    : now()->addDays(14);
                $productionDays = max(1, $bom->standard_production_days ?? 7);

                // Work backwards: end 1 day before delivery, start = end - production days
                $targetEndDate = $deliveryDate->copy()->subDay();
                $targetStartDate = $targetEndDate->copy()->subDays($productionDays - 1);

                // Ensure start date isn't in the past
                if ($targetStartDate->lt(now())) {
                    $targetStartDate = now()->addDay();
                    $targetEndDate = $targetStartDate->copy()->addDays($productionDays - 1);
                }

                // ── Create Production Order via unified gateway ──────────────────
                $actor = User::findOrFail($userId);
                $poService = app(\App\Domains\Production\Services\ProductionOrderService::class);
                $productionOrder = $poService->store([
                    'delivery_schedule_id' => $schedule->id,
                    'delivery_schedule_item_id' => $dsItem->id,
                    'client_order_id' => $order->id,
                    'source_type' => 'client_order',
                    'source_id' => $order->id,
                    'product_item_id' => $dsItem->product_item_id,
                    'bom_id' => $bom->id,
                    'qty_required' => $deficit,
                    'target_start_date' => $targetStartDate->toDateString(),
                    'target_end_date' => $targetEndDate->toDateString(),
                    'notes' => "Auto-created from Client Order {$order->order_reference}"
                        .($availableStock > 0 ? " (partial stock: {$availableStock} reserved, producing deficit: {$deficit})" : ''),
                ], $actor);

                // Update DSI status to in_production
                $dsItem->update(['status' => 'in_production']);

                // ── Gap #5: Notify production team ───────────────────────────────
                DB::afterCommit(fn () => ProductionOrderAutoCreated::dispatch($productionOrder->fresh(), $order->fresh()));
            }

            // Update parent DS item summary after processing all items
            $schedule->updateItemStatusSummary();
        }
    }
}
