<?php

declare(strict_types=1);

namespace App\Http\Controllers\VendorPortal;

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Services\VendorFulfillmentService;
use App\Domains\AP\Services\VendorItemService;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * VendorPortalController — vendor-facing endpoints for order management.
 *
 * All routes protected by auth:sanctum + vendor_scope middleware.
 * Vendor users can only access POs belonging to their vendor account.
 */
final class VendorPortalController extends Controller
{
    public function __construct(
        private readonly VendorFulfillmentService $fulfillmentService,
        private readonly VendorItemService $itemService,
    ) {}

    // ── Orders ───────────────────────────────────────────────────────────────

    /**
     * GET /vendor-portal/orders
     * List POs for the authenticated vendor (status = sent or partially_received).
     */
    public function orders(Request $request): JsonResponse
    {
        $vendorId = app('vendor_scope.vendor_id');

        $query = PurchaseOrder::with(['items', 'purchaseRequest'])
            ->where('vendor_id', $vendorId)
            ->whereIn('status', ['sent', 'partially_received', 'fully_received', 'closed']);

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        $orders = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($orders);
    }

    /**
     * GET /vendor-portal/orders/{purchaseOrder}
     * Show a single PO with items and fulfillment history.
     */
    public function orderDetail(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertVendorOwns($purchaseOrder);

        $purchaseOrder->load(['items', 'purchaseRequest', 'fulfillmentNotes.vendorUser', 'goodsReceipts']);

        return response()->json(['data' => $purchaseOrder]);
    }

    /**
     * POST /vendor-portal/orders/{purchaseOrder}/in-transit
     * Notify that goods have been dispatched.
     */
    public function markInTransit(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertVendorOwns($purchaseOrder);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $note = $this->fulfillmentService->markInTransit(
            $purchaseOrder,
            $request->user(),
            $validated['notes'] ?? ''
        );

        return response()->json([
            'message' => 'In-transit notification recorded.',
            'data'    => $note,
        ]);
    }

    /**
     * POST /vendor-portal/orders/{purchaseOrder}/deliver
     * Confirm delivery and auto-create a Goods Receipt draft.
     *
     * Body: { notes?: string, items: [{po_item_id: int, qty_delivered: float}] }
     */
    public function markDelivered(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertVendorOwns($purchaseOrder);

        $validated = $request->validate([
            'notes'                => ['nullable', 'string', 'max:1000'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.po_item_id'   => ['required', 'integer'],
            'items.*.qty_delivered' => ['required', 'numeric', 'min:0.001'],
        ]);

        $note = $this->fulfillmentService->markDelivered(
            $purchaseOrder,
            $request->user(),
            $validated['items'],
            $validated['notes'] ?? ''
        );

        return response()->json([
            'message' => 'Delivery confirmed. A Goods Receipt draft has been created for review.',
            'data'    => $note,
        ], 201);
    }

    // ── Items ────────────────────────────────────────────────────────────────

    /**
     * GET /vendor-portal/items
     * List the vendor's catalog items (active only by default).
     */
    public function items(Request $request): JsonResponse
    {
        $vendorId = app('vendor_scope.vendor_id');
        $vendor = Vendor::findOrFail($vendorId);

        $activeOnly = $request->query('active_only', 'true') !== 'false';
        $items = $this->itemService->list($vendor, $activeOnly);

        return response()->json(['data' => $items]);
    }

    /**
     * POST /vendor-portal/items
     * Create a new catalog item for the vendor's own catalog.
     */
    public function storeItem(Request $request): JsonResponse
    {
        $vendorId = app('vendor_scope.vendor_id');
        $vendor = Vendor::findOrFail($vendorId);

        $validated = $request->validate([
            'item_code'      => ['required', 'string', 'max:50'],
            'item_name'      => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'unit_of_measure' => ['required', 'string', 'max:20'],
            'unit_price'     => ['required', 'numeric', 'min:0'],
            'is_active'      => ['boolean'],
        ]);

        $item = $this->itemService->create($vendor, $validated, $request->user());

        return response()->json(['data' => $item], 201);
    }

    /**
     * PATCH /vendor-portal/items/{item}
     * Update a vendor catalog item.
     */
    public function updateItem(Request $request, int $item): JsonResponse
    {
        $vendorId = app('vendor_scope.vendor_id');
        $vendor = Vendor::findOrFail($vendorId);

        $vendorItem = $vendor->vendorItems()->findOrFail($item);

        $validated = $request->validate([
            'item_name'      => ['sometimes', 'string', 'max:200'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'unit_of_measure' => ['sometimes', 'string', 'max:20'],
            'unit_price'     => ['sometimes', 'numeric', 'min:0'],
            'is_active'      => ['boolean'],
        ]);

        $updated = $this->itemService->update($vendorItem, $validated, $request->user());

        return response()->json(['data' => $updated]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function assertVendorOwns(PurchaseOrder $po): void
    {
        $vendorId = app('vendor_scope.vendor_id');

        if ((int) $po->vendor_id !== $vendorId) {
            abort(403, 'Access denied — this Purchase Order does not belong to your vendor account.');
        }
    }
}
