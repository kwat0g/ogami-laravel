<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Services\GoodsReceiptService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreGoodsReceiptRequest;
use App\Http\Resources\Procurement\GoodsReceiptResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $service,
    ) {}

    /**
     * List GRs.
     *   ?status=draft|confirmed
     *   ?purchase_order_id=12
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        $query = GoodsReceipt::with(['purchaseOrder', 'receivedBy'])
            ->when($request->boolean('with_archived'), fn ($q) => $q->withTrashed())
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->input('status')),
            )
            ->when(
                $request->filled('purchase_order_id'),
                fn ($q) => $q->where('purchase_order_id', $request->integer('purchase_order_id')),
            )
            ->orderByDesc('created_at');

        return GoodsReceiptResource::collection($query->paginate(25));
    }

    public function store(StoreGoodsReceiptRequest $request): GoodsReceiptResource
    {
        $this->authorize('create', GoodsReceipt::class);

        $validated = $request->validated();
        $po = PurchaseOrder::findOrFail($validated['purchase_order_id']);

        $gr = $this->service->store(
            po: $po,
            data: $validated,
            items: $validated['items'],
            actor: auth()->user(),
        );

        return new GoodsReceiptResource($gr->load(['purchaseOrder', 'receivedBy', 'items']));
    }

    public function show(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('view', $goodsReceipt);

        return new GoodsReceiptResource(
            $goodsReceipt->load(['purchaseOrder.vendor', 'receivedBy', 'confirmedBy', 'items.poItem'])
        );
    }

    public function confirm(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('confirm', $goodsReceipt);

        $gr = $this->service->confirm($goodsReceipt->load('items'), auth()->user());

        return new GoodsReceiptResource($gr->load(['purchaseOrder', 'confirmedBy', 'items']));
    }

    public function destroy(GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->authorize('delete', $goodsReceipt);

        if ($goodsReceipt->status !== 'draft') {
            return response()->json([
                'success' => false,
                'error_code' => 'GR_NOT_DRAFT',
                'message' => 'Only draft Goods Receipts can be cancelled.',
            ], 422);
        }

        $goodsReceipt->items()->delete();
        $goodsReceipt->delete();

        return response()->json(['success' => true, 'message' => 'Goods Receipt cancelled.']);
    }
}
