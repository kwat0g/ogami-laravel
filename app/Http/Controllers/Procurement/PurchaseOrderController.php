<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Procurement\Services\PurchaseOrderService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
    ) {}

    /**
     * List POs.
     *   ?status=draft|sent|partially_received|fully_received|closed|cancelled
     *   ?vendor_id=5
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $query = PurchaseOrder::with(['vendor', 'purchaseRequest', 'createdBy'])
            ->when($request->boolean('with_archived'), fn ($q) => $q->withTrashed())
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->input('status')),
            )
            ->when(
                $request->filled('vendor_id'),
                fn ($q) => $q->where('vendor_id', $request->integer('vendor_id')),
            )
            ->orderByDesc('created_at');

        return PurchaseOrderResource::collection($query->paginate(25));
    }

    public function store(StorePurchaseOrderRequest $request): PurchaseOrderResource
    {
        $this->authorize('create', PurchaseOrder::class);

        $validated = $request->validated();
        $pr = PurchaseRequest::findOrFail($validated['purchase_request_id']);

        $po = $this->service->store(
            pr:    $pr,
            data:  $validated,
            items: $validated['items'],
            actor: auth()->user(),
        );

        return new PurchaseOrderResource($po->load(['vendor', 'purchaseRequest', 'items']));
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('view', $purchaseOrder);

        return new PurchaseOrderResource(
            $purchaseOrder->load(['vendor', 'purchaseRequest', 'createdBy', 'items', 'goodsReceipts'])
        );
    }

    public function update(StorePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('update', $purchaseOrder);

        if ($purchaseOrder->status !== 'draft') {
            abort(422, 'Only draft POs can be edited.');
        }

        $purchaseOrder->update($request->only(['delivery_date', 'payment_terms', 'delivery_address', 'notes']));

        return new PurchaseOrderResource($purchaseOrder->fresh()->load(['vendor', 'items']));
    }

    public function send(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('send', $purchaseOrder);

        $po = $this->service->send($purchaseOrder);

        return new PurchaseOrderResource($po->load(['vendor', 'items']));
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        $request->validate(['reason' => ['required', 'string', 'min:5']]);

        $this->service->cancel($purchaseOrder, $request->string('reason')->value());

        return response()->json(['success' => true, 'message' => 'Purchase Order cancelled.']);
    }
}
