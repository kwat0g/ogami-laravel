<?php

declare(strict_types=1);

namespace App\Http\Controllers\CRM;

use App\Domains\CRM\Models\ClientOrder;
use App\Domains\CRM\Services\ClientOrderService;
use App\Domains\Inventory\Models\ItemMaster;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ClientOrderController extends Controller
{
    public function __construct(
        private readonly ClientOrderService $service
    ) {}

    /**
     * List orders for sales review (sales dashboard)
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'customer_id', 'date_from', 'date_to']);
        $orders = $this->service->getOrdersForReview($filters, (int) $request->input('per_page', 20));

        return response()->json($orders);
    }

    /**
     * List orders for current customer (client portal)
     */
    public function myOrders(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->client_id) {
            return response()->json(['message' => 'No customer associated with this account'], 403);
        }

        $orders = $this->service->getOrdersForCustomer(
            $user->client_id,
            $request->only(['status']),
            (int) $request->input('per_page', 20)
        );

        return response()->json($orders);
    }

    /**
     * Get single order details
     */
    public function show(ClientOrder $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order = $this->service->refreshFulfillmentStatus($order);

        return response()->json($order->load([
            'items.itemMaster',
            'customer',
            'activities.user',
            'deliverySchedule',
            'deliverySchedules.deliverySchedule',
            'deliverySchedules.deliverySchedule.deliveryReceipts',
        ]));
    }

    /**
     * Submit new order from client portal
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ClientOrder::class);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_master_id' => 'required|integer|exists:item_masters,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price_centavos' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
            'requested_delivery_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $order = $this->service->submitOrder(
            customerId: $user->client_id,
            items: $validated['items'],
            requestedDate: $validated['requested_delivery_date'] ?? null,
            notes: $validated['notes'] ?? null,
            submittedByUserId: $user->id
        );

        return response()->json($order, 201);
    }

    /**
     * Update a pending order (client can edit items, notes, delivery date)
     */
    public function update(Request $request, ClientOrder $order): JsonResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_master_id' => 'required|integer|exists:item_masters,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price_centavos' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
            'requested_delivery_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $order = $this->service->updateOrder(
            order: $order,
            items: $validated['items'],
            requestedDate: $validated['requested_delivery_date'] ?? null,
            notes: $validated['notes'] ?? null,
            userId: $user->id
        );

        return response()->json($order);
    }

    /**
     * Approve order (sales)
     */
    public function approve(Request $request, ClientOrder $order): JsonResponse
    {
        $this->authorize('approve', $order);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $order = $this->service->approveOrder($order, $user->id, $validated['notes'] ?? null);

        return response()->json($order);
    }

    /**
     * Reject order (sales)
     */
    public function reject(Request $request, ClientOrder $order): JsonResponse
    {
        $this->authorize('reject', $order);

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $order = $this->service->rejectOrder(
            $order,
            $validated['reason'],
            $user->id,
            $validated['notes'] ?? null
        );

        return response()->json($order);
    }

    /**
     * Negotiate order terms (sales)
     */
    public function negotiate(Request $request, ClientOrder $order): JsonResponse
    {
        $this->authorize('negotiate', $order);

        $validated = $request->validate([
            'reason' => 'required|string|in:stock_low,production_delay,price_change,partial_fulfillment,other',
            'proposed_changes' => 'nullable|array',
            'proposed_changes.delivery_date' => 'nullable|date',
            'proposed_changes.items' => 'nullable|array',
            'proposed_changes.items.*.item_id' => 'required_with:proposed_changes.items|integer',
            'proposed_changes.items.*.quantity' => 'nullable|numeric|min:0',
            'proposed_changes.items.*.price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $order = $this->service->negotiateOrder(
            $order,
            $validated['reason'],
            $validated['proposed_changes'] ?? [],
            $user->id,
            $validated['notes'] ?? null
        );

        return response()->json($order);
    }

    /**
     * Client responds to negotiation
     */
    public function respond(Request $request, ClientOrder $order): JsonResponse
    {
        $this->authorize('respond', $order);

        $validated = $request->validate([
            'response' => 'required|string|in:accept,counter,cancel',
            'counter_proposals' => 'nullable|array',
            'counter_proposals.delivery_date' => 'nullable|date',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $order = $this->service->clientRespond(
            $order,
            $validated['response'],
            $validated['counter_proposals'] ?? null,
            $user->id
        );

        return response()->json($order);
    }

    /**
     * Client cancels their order (only when pending)
     */
    public function cancel(Request $request, ClientOrder $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        /** @var User $user */
        $user = Auth::user();

        $order = $this->service->cancelOrder($order, $user->id);

        return response()->json($order);
    }

    /**
     * Sales responds to client counter-proposal (accept, counter, or reject)
     */
    public function salesRespond(Request $request, ClientOrder $order): JsonResponse
    {
        $this->authorize('salesRespond', $order);

        $validated = $request->validate([
            'response' => 'required|string|in:accept,counter,reject',
            'counter_proposals' => 'nullable|array',
            'counter_proposals.delivery_date' => 'nullable|date',
            'counter_proposals.items' => 'nullable|array',
            'counter_proposals.items.*.item_id' => 'required_with:counter_proposals.items|integer',
            'counter_proposals.items.*.quantity' => 'nullable|numeric|min:0',
            'counter_proposals.items.*.price' => 'nullable|numeric|min:0',
            'counter_proposals.reason' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $order = $this->service->salesRespondToCounter(
            $order,
            $validated['response'],
            $validated['counter_proposals'] ?? null,
            $validated['notes'] ?? null,
            $user->id
        );

        return response()->json($order);
    }

    /**
     * VP approves high-value order (vp_pending → approved)
     */
    public function vpApprove(Request $request, ClientOrder $order): JsonResponse
    {
        $this->authorize('vpApprove', $order);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $order = $this->service->vpApproveOrder($order, $user->id, $validated['notes'] ?? null);

        return response()->json($order);
    }

    /**
     * Explicitly force production for an already approved order.
     */
    public function forceProduction(Request $request, ClientOrder $order): JsonResponse
    {
        $this->authorize('forceProduction', $order);

        $validated = $request->validate([
            'mode' => 'required|string|in:preserve_stock_produce_full,consume_stock_then_replenish,stock_aware_produce_deficit,per_item',
            'reason' => 'required|string|max:500',
            'items' => 'nullable|array',
            'items.*.item_master_id' => 'required_with:items|integer|exists:item_masters,id',
            'items.*.mode' => 'required_with:items|string|in:preserve_stock_produce_full,consume_stock_then_replenish,stock_aware_produce_deficit',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $order = $this->service->forceProductionFromOrder(
            order: $order,
            userId: $user->id,
            mode: $validated['mode'],
            reason: $validated['reason'],
            itemModes: $validated['items'] ?? [],
        );

        return response()->json($order);
    }

    /**
     * Get stock availability for each item in a client order.
     * Used by the frontend to show stock info before force production.
     */
    public function stockAvailability(ClientOrder $order): JsonResponse
    {
        $this->authorize('view', $order);

        $availability = $this->service->getStockAvailability($order);

        return response()->json([
            'success' => true,
            'data' => $availability,
        ]);
    }

    /**
     * Get available products for client portal shop
     */
    public function availableProducts(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Both client portal users and regular users can view products
        if (! $user->client_id && ! $user->hasPermissionTo('inventory.items.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get finished goods (products) that are active
        $products = ItemMaster::query()
            ->where('type', 'finished_good')
            ->where('is_active', true)
            ->select([
                'id', 'item_code', 'name', 'description',
                'unit_of_measure', 'type', 'category_id',
                'standard_price_centavos', 'is_active',
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('item_code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->paginate((int) $request->input('per_page', 20));

        return response()->json($products);
    }
}
